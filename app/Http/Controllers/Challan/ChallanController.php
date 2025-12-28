<?php

namespace App\Http\Controllers\Challan;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Item;
use App\Models\File;
use App\Models\Challan;
use App\Models\Supplier_item_list;
use App\Models\Expense;
use App\Models\Challan_item;
use App\Models\Supplier;
use App\Helpers\ActivityHelper;
use App\Services\FileUploadService;
use App\Traits\ApiResponser;

class ChallanController extends Controller
{
    use ApiResponser;
    public function store(Request $request)
    {
        try {
            // Get the authenticated user
            $user = auth()->user();

            // Validate the request data
            $validator = Validator::make($request->all(), [
                'Date' => 'required|date',
                'item_id' => 'required|array',
                'item_id.*' => 'exists:items,id',
                'delivery_price' => 'required|numeric|min:0',
                'supplier_id' => 'required|exists:suppliers,id',
                'buying' => 'required|array',
                'buying.*' => 'numeric|min:0',
                'quantity' => 'required|array',
                'quantity.*' => 'integer|min:1',
                'invoice' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:4048',
            ]);

            // If validation fails, return error response
            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            // Calculate the total buying price
            $totalBuyingPrice = 0;
            foreach ($request->item_id as $index => $itemId) {
                $totalBuyingPrice += $request->buying[$index] * $request->quantity[$index];
            }

            // Calculate the total (buying + delivery)
            $total = $totalBuyingPrice + $request->delivery_price;

            // Create the challan
            $challan = Challan::create([
                'Date' => $request->Date,
                'user_id' => $user->id,
                'total' => $total,
                'delivery_price' => $request->delivery_price,
                'supplier_id' => $request->supplier_id,
            ]);

            // Save in expense
            Expense::create([
                'date' => $request->Date,
                'user_id' => $user->id,
                'title' => 'Buying equipments',
                'amount' => $total,
                'description' => "Buying Equipment on {$request->Date}. Total price is {$total}.",
            ]);

            // Process each item
            foreach ($request->item_id as $index => $itemId) {
                // Find item details
                $item = Item::find($itemId);

                // Save to Supplier_item_list
                Supplier_item_list::create([
                    'supplier_id' => $request->supplier_id,
                    'item_id' => $itemId,
                    'price' => $request->buying[$index],
                    'quantity' => $request->quantity[$index],
                    'challan_id' => $challan->id,
                ]);

                // Save to Challan_item with item_name
                Challan_item::create([
                    'challan_id' => $challan->id,
                    'item_id' => $itemId,
                    'item_name' => $item->name,
                    'quantity' => $request->quantity[$index],
                    'buying_price' => $request->buying[$index],
                ]);

                // Update item quantity
                $item->quantity += $request->quantity[$index];
                $item->save();
            }

            // Save the invoice image
            if ($request->hasFile('invoice')) {
                $path = FileUploadService::upload(
                    $request->file('invoice'),
                    'challan',
                    'zantech'
                );

                File::create([
                    'relatable_id' => $challan->id,
                    'type'         => 'challan',
                    'path'         => $path,
                ]);
            }

            // Build item details string
            $itemDetails = '';
            foreach ($request->item_id as $index => $itemId) {
                $item = Item::find($itemId);
                $itemDetails .= "Item: {$item->name}, Qty: {$request->quantity[$index]}, Price: {$request->buying[$index]}; ";
            }

            // Optional: Get supplier name
            $supplier = Supplier::find($request->supplier_id);

            // Build full activity description
            $challanDesc = "Created Challan ID: {$challan->id}, Supplier: {$supplier->name}, Total: {$total}, Delivery: {$request->delivery_price}, Items: [{$itemDetails}] Date: " . now()->toDateTimeString();

            // Save activity
            ActivityHelper::logActivity($challan->id, 'Challan', $challanDesc);

            return $this->created($challan, 'Challan created successfully.');
        } catch (\Exception $e) {
            return $this->error('An error occurred while creating the challan.', 500, $e->getMessage());
        }
    }

    // Show single challenge
    public function show($id)
    {
        try {
            $challan = Challan::with(['supplier', 'user', 'challanItems', 'invoices'])->find($id);

            if (!$challan) {
                return $this->notFound('Challan not found.');
            }

            $response = [
                'id' => $challan->id,
                'Date' => $challan->Date,
                'totalproductprice' => $challan->total - $challan->delivery_price,
                'delivery_price' => $challan->delivery_price,
                'total' => $challan->total,
                'supplier' => $challan->supplier ? [
                    'name' => $challan->supplier->name,
                    'phone' => $challan->supplier->phone,
                    'address' => $challan->supplier->address,
                ] : null,
                'user' => [
                    'name' => $challan->user->name,
                ],
                'items' => $challan->challanItems->map(function ($item) {
                    return [
                        'challan_item_id' => $item->id,
                        'item_id' => $item->item_id,
                        'item_name' => $item->item_name,
                        'buying_price' => $item->buying_price,
                        'quantity' => $item->quantity,
                    ];
                }),
                'invoices' => $challan->invoices->map(function ($file) {
                    return [
                        'id' => $file->id,
                        'path' => FileUploadService::getUrl($file->path),
                    ];
                }),
            ];

            return $this->success($response, 'Challan retrieved successfully.');
        } catch (\Exception $e) {
            return $this->error('An error occurred while retrieving the challan.', 500, $e->getMessage());
        }
    }

    // show all challenges
    public function index(Request $request)
    {
        try {
            // Get inputs
            $perPage = $request->input('limit');
            $currentPage = $request->input('page');
            $search = $request->input('search');
            $date = $request->input('date');
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');

            // Base query
            $query = Challan::with('supplier')->orderBy('id', 'desc');

            // Search filter
            if ($search) {
                $query->whereHas('supplier', function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                        ->orWhere('phone', 'like', '%' . $search . '%');
                });
            }

            // Exact date filter (expects YYYY-MM-DD)
            if ($date) {
                $query->whereDate('Date', $date);
            }

            // Date range filter (expects YYYY-MM-DD format)
            if ($startDate && $endDate) {
                $query->whereBetween('Date', [$startDate, $endDate]);
            }

            // Pagination
            if ($perPage && $currentPage) {
                if (!is_numeric($perPage) || !is_numeric($currentPage) || $perPage <= 0 || $currentPage <= 0) {
                    return $this->validationError('Invalid pagination parameters.');
                }

                $challans = $query->paginate($perPage, ['*'], 'page', $currentPage);

                $formattedChallans = $challans->map(function ($challan) {
                    return [
                        'id' => $challan->id,
                        'Date' => $challan->Date,
                        'totalproductprice' => $challan->total - $challan->delivery_price,
                        'delivery_price' => $challan->delivery_price,
                        'total' => $challan->total,
                        'supplier' => $challan->supplier ? [
                            'name' => $challan->supplier->name,
                            'phone' => $challan->supplier->phone,
                            'address' => $challan->supplier->address,
                        ] : null,
                    ];
                });

                return response()->json([
                    'success' => true,
                    'status' => 200,
                    'message' => 'Challans retrieved successfully.',
                    'data' => $formattedChallans,
                    'pagination' => [
                        'total_rows' => $challans->total(),
                        'current_page' => $challans->currentPage(),
                        'per_page' => $challans->perPage(),
                        'total_pages' => $challans->lastPage(),
                        'has_more_pages' => $challans->hasMorePages(),
                    ]
                ], 200);
            }

            // Without pagination
            $challans = $query->get();

            $formattedChallans = $challans->map(function ($challan) {
                return [
                    'id' => $challan->id,
                    'Date' => $challan->Date,
                    'total' => $challan->total,
                    'delivery_price' => $challan->delivery_price,
                    'supplier' => $challan->supplier ? [
                        'name' => $challan->supplier->name,
                        'phone' => $challan->supplier->phone,
                        'address' => $challan->supplier->address,
                    ] : null,
                ];
            });

            return $this->success($formattedChallans, 'Challans retrieved successfully.');
        } catch (\Exception $e) {
            return $this->error('An error occurred while retrieving challans.', 500, $e->getMessage());
        }
    }


    // update challenge
    public function update(Request $request, $id)
    {
        try {
            // Find the challan by ID
            $challan = Challan::find($id);

            if (!$challan) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Challan not found.',
                    'data' => null,
                    'errors' => 'Invalid challan ID.',
                ], 404);
            }

            // Validate the request data
            $validator = Validator::make($request->all(), [
                'Date' => 'required|date',
                'delivery_price' => 'required|numeric|min:0',
                'supplier_id' => 'required|exists:suppliers,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'status' => 422,
                    'message' => 'Validation failed.',
                    'data' => null,
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Capture old values
            $oldDate = $challan->Date;
            $oldDeliveryPrice = $challan->delivery_price;
            $oldSupplierId = $challan->supplier_id;
            $oldTotal = $challan->total;

            // Recalculate the total
            $newDeliveryPrice = $request->delivery_price;
            $priceDifference = $newDeliveryPrice - $oldDeliveryPrice;
            $updatedTotal = $challan->total + $priceDifference;

            // Update challan
            $challan->update([
                'Date' => $request->Date,
                'delivery_price' => $newDeliveryPrice,
                'supplier_id' => $request->supplier_id,
                'total' => $updatedTotal,
            ]);

            // Get supplier name
            $newSupplier = Supplier::find($request->supplier_id);

            // Prepare activity description
            $challanDesc = "Updated Challan ID: {$challan->id} on " . now()->toDateTimeString() . ". Changes: ";
            $challanDesc .= "Date: {$oldDate} → {$request->Date}, ";
            $challanDesc .= "Delivery Price: {$oldDeliveryPrice} → {$newDeliveryPrice}, ";
            $challanDesc .= "Supplier ID: {$oldSupplierId} → {$request->supplier_id} ({$newSupplier->name}), ";
            $challanDesc .= "Total: {$oldTotal} → {$updatedTotal}.";

            // Save activity
            ActivityHelper::logActivity($challan->id, 'Challan', $challanDesc);

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Challan updated successfully.',
                'data' => $challan,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while updating the challan.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // add item to challenge
    public function addItemsToChallan(Request $request, $challanId)
    {
        try {
            // Find the challan
            $challan = Challan::find($challanId);

            if (!$challan) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Challan not found.',
                    'data' => null,
                    'errors' => 'Invalid challan ID.',
                ], 404);
            }

            // Validate the request
            $validator = Validator::make($request->all(), [
                'item_id' => 'required|array',
                'item_id.*' => 'exists:items,id',
                'buying' => 'required|array',
                'buying.*' => 'numeric|min:0',
                'quantity' => 'required|array',
                'quantity.*' => 'integer|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'status' => 422,
                    'message' => 'Validation failed.',
                    'data' => null,
                    'errors' => $validator->errors(),
                ], 422);
            }

            $totalAdded = 0;

            foreach ($request->item_id as $index => $itemId) {
                $buyingPrice = $request->buying[$index];
                $quantity = $request->quantity[$index];

                $item = Item::find($itemId);

                if (!$item) {
                    continue;
                }

                $subtotal = $buyingPrice * $quantity;
                $totalAdded += $subtotal;

                // Save to Challan_item
                Challan_item::create([
                    'challan_id' => $challan->id,
                    'item_id' => $itemId,
                    'item_name' => $item->name,
                    'quantity' => $quantity,
                    'buying_price' => $buyingPrice,
                ]);

                // Update item quantity
                $item->quantity += $quantity;
                $item->save();
            }

            // Update challan total
            $challan->total += $totalAdded;
            $challan->save();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Items added and challan updated successfully.',
                'data' => $challan,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while adding items to the challan.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // items quantity update in challen items table
    public function updateChallanItemQuantity(Request $request, $challanItemId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'quantity' => 'required|integer|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'status' => 422,
                    'message' => 'Validation failed.',
                    'data' => null,
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Find the Challan Item
            $challanItem = Challan_item::find($challanItemId);
            if (!$challanItem) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Challan item not found.',
                    'data' => null,
                    'errors' => 'Invalid challan item ID.',
                ], 404);
            }

            // Find the related item
            $item = Item::find($challanItem->item_id);
            if (!$item) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Related item not found.',
                    'data' => null,
                    'errors' => 'Invalid item ID.',
                ], 404);
            }

            // Save old quantity and subtotal
            $oldQuantity = $challanItem->quantity;
            $oldSubtotal = $challanItem->buying_price * $oldQuantity;

            // Calculate new subtotal
            $newQuantity = $request->quantity;
            $newSubtotal = $challanItem->buying_price * $newQuantity;

            // Update item stock
            $quantityDifference = $newQuantity - $oldQuantity;
            $item->quantity += $quantityDifference;
            $item->save();

            // Update challan item
            $challanItem->quantity = $newQuantity;
            $challanItem->save();

            // Update challan total
            $challan = Challan::find($challanItem->challan_id);
            $oldTotal = $challan->total;
            $challan->total = $oldTotal - $oldSubtotal + $newSubtotal;
            $challan->save();

            // Log activity
            $activityDesc = "Updated Challan Item in Challan ID: {$challan->id} on " . now()->toDateTimeString() . ". ";
            $activityDesc .= "Item: {$item->name}, Quantity: {$oldQuantity} → {$newQuantity}, ";
            $activityDesc .= "Subtotal: {$oldSubtotal} → {$newSubtotal}, ";
            $activityDesc .= "Challan Total: {$oldTotal} → {$challan->total}.";

            ActivityHelper::logActivity($challan->id, 'Challan', $activityDesc);

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Challan item quantity and total updated successfully.',
                'data' => [
                    'challan_item' => $challanItem,
                    'updated_item' => $item,
                    'updated_challan' => $challan,
                ],
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while updating.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }


    // delete challenge item
    public function deleteChallanItem($challanItemId)
    {
        try {
            // Find the challan item
            $challanItem = Challan_item::find($challanItemId);

            if (!$challanItem) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Challan item not found.',
                    'data' => null,
                    'errors' => 'Invalid challan item ID.',
                ], 404);
            }

            // Get related item and challan
            $item = Item::find($challanItem->item_id);
            $challan = Challan::find($challanItem->challan_id);

            if (!$item || !$challan) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Related item or challan not found.',
                    'data' => null,
                    'errors' => 'Invalid related data.',
                ], 404);
            }

            // Calculate subtotal to subtract from challan total
            $subtotal = $challanItem->buying_price * $challanItem->quantity;

            // Save old values for activity log
            $oldItemQuantity = $item->quantity;
            $oldChallanTotal = $challan->total;

            // Decrease item quantity
            $item->quantity -= $challanItem->quantity;
            if ($item->quantity < 0) {
                $item->quantity = 0;
            }
            $item->save();

            // Update challan total
            $challan->total -= $subtotal;
            if ($challan->total < 0) {
                $challan->total = 0;
            }
            $challan->save();

            // Delete challan item
            $challanItem->delete();

            // Log activity
            $activityDesc = "Deleted Challan Item from Challan ID: {$challan->id} on " . now()->toDateTimeString() . ". ";
            $activityDesc .= "Item: {$item->name}, Removed Qty: {$challanItem->quantity}, Subtotal Removed: {$subtotal}, ";
            $activityDesc .= "Item Quantity: {$oldItemQuantity} → {$item->quantity}, ";
            $activityDesc .= "Challan Total: {$oldChallanTotal} → {$challan->total}.";

            ActivityHelper::logActivity($challan->id, 'Challan', $activityDesc);

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Challan item deleted, item quantity and challan total updated.',
                'data' => [
                    'updated_item' => $item,
                    'updated_challan' => $challan,
                ],
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while deleting the challan item.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }


    // add new invoice image
    public function uploadInvoiceImage(Request $request, $challanId)
    {
        try {
            // Validate challan existence
            $challan = Challan::find($challanId);
            if (!$challan) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Challan not found.',
                    'data' => null,
                    'errors' => 'Invalid challan ID.',
                ], 404);
            }

            // Validate the uploaded file
            $validator = Validator::make($request->all(), [
                'invoice' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'status' => 422,
                    'message' => 'Validation failed.',
                    'data' => null,
                    'errors' => $validator->errors(),
                ], 422);
            }

            if ($request->hasFile('invoice')) {
                $path = FileUploadService::upload(
                    $request->file('invoice'),
                    'challan',
                    'zantech'
                );

                File::create([
                    'relatable_id' => $challan->id,
                    'type'         => 'challan',
                    'path'         => $path,
                ]);
            }


            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Invoice image uploaded successfully.',
                'data' => [],
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while uploading the invoice image.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // delete invoice image
    public function destroyInvoice($id)
    {
        try {
            // Find the file record
            $file = File::find($id);

            if (!$file) {
                return $this->notFound('Invoice file not found.');
            }

            // Delete the physical file from public directory
            FileUploadService::delete($file->path);

            // Delete the image record from the database
            $file->delete();

            return $this->success('Invoice file deleted successfully.');
        } catch (\Exception $e) {
            return $this->error('An error occurred while deleting the invoice file.', 500, $e->getMessage());
        }
    }
}
