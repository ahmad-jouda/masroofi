<?php

namespace App\Http\Controllers;

use App\Models\Envelope;
use App\Models\GroceryItem;
use App\Models\PartnerNotification;
use App\Models\Transaction;
use App\Services\DailySurplusAdjustmentService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GroceryItemController extends Controller
{
    /**
     * Get list of active grocery items (JSON).
     */
    public function index(Request $request): JsonResponse
    {
        $items = GroceryItem::with(['user', 'buyer'])
            ->active()
            ->orderBy('is_bought')
            ->orderBy('created_at', 'desc')
            ->get();

        $pendingCount = $items->where('is_bought', false)->count();
        $boughtCount = $items->where('is_bought', true)->count();
        $estimatedTotal = (float) $items->where('is_bought', false)->sum('estimated_price');

        return response()->json([
            'success' => true,
            'items' => $items,
            'counts' => [
                'pending' => $pendingCount,
                'bought' => $boughtCount,
                'total' => $items->count(),
                'estimated_total' => $estimatedTotal,
            ],
            'categories' => GroceryItem::CATEGORIES,
        ]);
    }

    /**
     * Store a newly created grocery item.
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|in:'.implode(',', array_keys(GroceryItem::CATEGORIES)),
            'estimated_price' => 'nullable|numeric|min:0',
        ]);

        $item = GroceryItem::create([
            'user_id' => $request->user()->id,
            'name' => trim($validated['name']),
            'category' => $validated['category'] ?? 'groceries',
            'estimated_price' => $validated['estimated_price'] ?? null,
            'is_bought' => false,
        ]);

        $item->load(['user']);

        // Notify partner
        $partnerName = $request->user()->name;
        PartnerNotification::notifyPartner(
            workspaceId: (int) $request->user()->workspace_id,
            actorId: (int) $request->user()->id,
            type: 'grocery_add',
            title: '🛒 إضافة لقائمة المقاضي',
            message: "👤 أضاف {$partnerName}: {$item->name}",
            icon: '🛒'
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'تمت إضافة الغرض بنجاح ✨',
                'item' => $item,
            ]);
        }

        return redirect()->route('dashboard')->with('success', 'تمت إضافة الغرض إلى قائمة المقاضي ✅');
    }

    /**
     * Toggle bought status of an item.
     */
    public function toggle(Request $request, GroceryItem $groceryItem): JsonResponse|RedirectResponse
    {
        $newStatus = ! $groceryItem->is_bought;
        $groceryItem->is_bought = $newStatus;
        $groceryItem->bought_by = $newStatus ? $request->user()->id : null;
        $groceryItem->bought_at = $newStatus ? Carbon::now() : null;
        $groceryItem->save();

        $groceryItem->load(['user', 'buyer']);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'item' => $groceryItem,
                'message' => $newStatus ? 'تم شطب الغرض كتم شراؤه ✔️' : 'تمت إعادة الغرض إلى القائمة 🔄',
            ]);
        }

        return back()->with('success', 'تم تحديث حالة الغرض بنجاح');
    }

    /**
     * Delete an item from the grocery list.
     */
    public function destroy(Request $request, GroceryItem $groceryItem): JsonResponse|RedirectResponse
    {
        $groceryItem->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'تم حذف الغرض من القائمة 🗑️',
            ]);
        }

        return back()->with('success', 'تم حذف الغرض من القائمة');
    }

    /**
     * Convert bought / checked grocery items into an active Expense transaction in Grocery envelope.
     */
    public function convertToExpense(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'envelope_id' => 'nullable|exists:envelopes,id',
            'payment_method' => 'required|in:card,cash,transfer,credit',
            'date' => 'required|date',
            'note' => 'nullable|string|max:255',
            'item_ids' => 'nullable|array',
            'item_ids.*' => 'integer|exists:grocery_items,id',
        ]);

        // Default envelope to Grocery ("بقالة") if not supplied
        $envelopeId = $validated['envelope_id'] ?? null;
        if (! $envelopeId) {
            $defaultEnvelope = Envelope::where('name', 'like', '%بقالة%')->first()
                ?? Envelope::first();
            $envelopeId = $defaultEnvelope?->id;
        }

        $itemsQuery = GroceryItem::active();
        if (! empty($validated['item_ids'])) {
            $itemsQuery->whereIn('id', $validated['item_ids']);
        } else {
            // Default to all checked items
            $itemsQuery->where('is_bought', true);
        }

        $itemsToConvert = $itemsQuery->get();

        if ($itemsToConvert->isEmpty()) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا توجد أغراض مشطوبة لتحويلها إلى مصروف.',
                ], 422);
            }

            return back()->with('error', 'يرجى تحديد أو شطب الأغراض المراد تحويلها لمصروف أولاً.');
        }

        $itemNames = $itemsToConvert->pluck('name')->take(5)->implode('، ');
        if ($itemsToConvert->count() > 5) {
            $itemNames .= ' وأخرى...';
        }

        $note = $validated['note'] ?: "مقاضي البيت ({$itemsToConvert->count()} أصناف: {$itemNames})";

        $transaction = null;
        $adjustmentMessage = null;

        DB::transaction(function () use ($validated, $envelopeId, $note, $itemsToConvert, $request, &$transaction) {
            $transaction = Transaction::create([
                'type' => 'expense',
                'amount' => $validated['amount'],
                'envelope_id' => $envelopeId,
                'goal_id' => null,
                'date' => $validated['date'],
                'payment_method' => $validated['payment_method'],
                'note' => $note,
            ]);

            // Mark all items as bought and associate with this transaction
            foreach ($itemsToConvert as $item) {
                $item->update([
                    'is_bought' => true,
                    'bought_by' => $request->user()->id,
                    'bought_at' => Carbon::now(),
                    'transaction_id' => $transaction->id,
                ]);
            }
        });

        // Recalculate surplus if past date
        if (Carbon::parse($validated['date'])->lt(Carbon::today())) {
            $adjustment = DailySurplusAdjustmentService::recalculateForDate($validated['date']);
            if ($adjustment) {
                $adjustmentMessage = DailySurplusAdjustmentService::formatAdjustmentSummary($adjustment);
            }
        }

        // Notify partner
        $partnerName = $request->user()->name;
        $formattedAmount = number_format((float) $validated['amount'], 2);
        PartnerNotification::notifyPartner(
            workspaceId: (int) $request->user()->workspace_id,
            actorId: (int) $request->user()->id,
            type: 'grocery_converted',
            title: '💳 تحويل مقاضي البيت لمصروف',
            message: "👤 اشترى {$partnerName} مقاضي البيت وسجل مصروفاً بقيمة {$formattedAmount} ₪",
            icon: '🛍️'
        );

        $msg = "تم تحويل {$itemsToConvert->count()} أصناف إلى مصروف بقيمة {$formattedAmount} ₪ بنجاح ✅";
        if ($adjustmentMessage) {
            $msg .= ' '.$adjustmentMessage;
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'transaction' => $transaction->load(['envelope']),
                'converted_count' => $itemsToConvert->count(),
            ]);
        }

        return redirect()->route('dashboard')->with('success', $msg);
    }

    /**
     * Batch sync offline grocery changes (additions, toggles, conversions).
     */
    public function syncOffline(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'new_items' => 'nullable|array',
            'new_items.*.name' => 'required|string|max:255',
            'new_items.*.category' => 'nullable|string',
            'new_items.*.estimated_price' => 'nullable|numeric',
            'toggles' => 'nullable|array',
            'toggles.*.id' => 'required|integer',
            'toggles.*.is_bought' => 'required|boolean',
            'deletes' => 'nullable|array',
            'deletes.*' => 'integer',
        ]);

        $createdItems = [];
        $userId = $request->user()->id;
        $workspaceId = $request->user()->workspace_id;

        DB::transaction(function () use ($validated, $userId, &$createdItems) {
            // Handle deletions
            if (! empty($validated['deletes'])) {
                GroceryItem::whereIn('id', $validated['deletes'])->delete();
            }

            // Handle toggles
            if (! empty($validated['toggles'])) {
                foreach ($validated['toggles'] as $toggle) {
                    $item = GroceryItem::find($toggle['id']);
                    if ($item) {
                        $item->is_bought = (bool) $toggle['is_bought'];
                        $item->bought_by = $item->is_bought ? $userId : null;
                        $item->bought_at = $item->is_bought ? Carbon::now() : null;
                        $item->save();
                    }
                }
            }

            // Handle new items
            if (! empty($validated['new_items'])) {
                foreach ($validated['new_items'] as $itemData) {
                    $category = $itemData['category'] ?? 'groceries';
                    if (! array_key_exists($category, GroceryItem::CATEGORIES)) {
                        $category = 'groceries';
                    }

                    $created = GroceryItem::create([
                        'user_id' => $userId,
                        'name' => trim($itemData['name']),
                        'category' => $category,
                        'estimated_price' => $itemData['estimated_price'] ?? null,
                        'is_bought' => ! empty($itemData['is_bought']),
                        'bought_by' => ! empty($itemData['is_bought']) ? $userId : null,
                        'bought_at' => ! empty($itemData['is_bought']) ? Carbon::now() : null,
                    ]);
                    $createdItems[] = $created;
                }
            }
        });

        // Notify partner if items were synced
        if (! empty($createdItems)) {
            $partnerName = $request->user()->name;
            $itemsSummary = collect($createdItems)->pluck('name')->take(3)->implode('، ');
            PartnerNotification::notifyPartner(
                workspaceId: (int) $workspaceId,
                actorId: (int) $userId,
                type: 'grocery_sync',
                title: '🛒 مزامنة مقاضي البيت أوفلاين',
                message: "👤 زامن {$partnerName} أصنافاً جديدة: {$itemsSummary}",
                icon: '🛒'
            );
        }

        $allActive = GroceryItem::with(['user', 'buyer'])
            ->active()
            ->orderBy('is_bought')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'تمت مزامنة قائمة المقاضي بنجاح 🔄',
            'created' => $createdItems,
            'items' => $allActive,
        ]);
    }
}
