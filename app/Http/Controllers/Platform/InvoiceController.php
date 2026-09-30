<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Resources\Platform\InvoiceResource;
use App\Models\Invoice;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $query = Invoice::with(['tenant', 'subscription.plan'])->latest();

        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->integer('tenant_id'));
        }

        return InvoiceResource::collection($query->paginate(20));
    }

    public function show(Invoice $invoice)
    {
        return response()->json([
            'data' => InvoiceResource::make($invoice->load(['tenant', 'subscription.plan', 'payment'])),
        ]);
    }

    public function view(Invoice $invoice)
    {
        return view('invoices.show', [
            'invoice' => $invoice->load(['tenant', 'subscription.plan', 'payment']),
        ]);
    }
}
