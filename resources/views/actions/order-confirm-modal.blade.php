<style>
    .modal-container {
        background-color: #fff;
        padding: 30px;
        border-radius: 10px;
        box-shadow: 0 0 10px rgba(0,0,0,0.1);
        margin: 0 auto;
        font-family: Arial, sans-serif;
        color: #333;
    }
    .modal-header {
        text-align: center;
        border-bottom: 2px solid #007bff;
        padding-bottom: 20px;
        margin-bottom: 30px;
    }
    .modal-header h2 {
        color: #007bff;
        margin: 0;
        font-size: 28px;
    }
    .order-info {
        background-color: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 25px;
    }
    .order-info h3 {
        color: #495057;
        margin-top: 0;
        border-bottom: 1px solid #dee2e6;
        padding-bottom: 10px;
    }
    .info-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 10px;
        padding: 5px 0;
    }
    .info-label {
        font-weight: bold;
        color: #495057;
    }
    .info-value {
        color: #6c757d;
    }
    .address-section {
        display: flex;
        justify-content: space-between;
        margin-bottom: 25px;
    }
    .address-box {
        width: 48%;
        background-color: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
    }
    .address-box h4 {
        margin-top: 0;
        color: #495057;
        border-bottom: 1px solid #dee2e6;
        padding-bottom: 8px;
    }
    .items-section {
        margin-bottom: 25px;
    }
    .items-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }
    .items-table th,
    .items-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #dee2e6;
    }
    .items-table th {
        background-color: #007bff;
        color: white;
        font-weight: bold;
    }
    .items-table tr:nth-child(even) {
        background-color: #f8f9fa;
    }
    .total-section {
        background-color: #e9ecef;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 25px;
    }
    .total-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 10px;
        font-size: 16px;
    }
    .total-final {
        font-weight: bold;
        font-size: 18px;
        color: #007bff;
        border-top: 2px solid #007bff;
        padding-top: 10px;
    }
    .footer {
        text-align: center;
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid #dee2e6;
        color: #6c757d;
        font-size: 14px;
    }
    @media (max-width: 700px) {
        .modal-container { padding: 10px; }
        .address-section { flex-direction: column; }
        .address-box { width: 100%; margin-bottom: 15px; }
        .info-row { flex-direction: column; }
        .items-table { font-size: 14px; }
    }
</style>

<div class="modal-container">
    <div class="modal-header">
        <h2>Order Confirmation</h2>
        <p>Order #{{ $order->order_number ?? $order->id }}</p>
    </div>

    <div class="order-info">
        <h3>Order Details</h3>
        <div class="info-row">
            <span class="info-label">Order Number:</span>
            <span class="info-value">#{{ $order->order_number ?? $order->id }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Order Date:</span>
            <span class="info-value">{{ $order->created_at ? $order->created_at->format('F j, Y \a\t g:i A') : '' }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Status:</span>
            <span class="info-value">{{ ucfirst($order->status ?? 'Pending') }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Payment Status:</span>
            <span class="info-value">{{ ucfirst($order->payment_status ?? 'Pending') }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Payment Method:</span>
            <span class="info-value">{{ ucfirst($order->payment_method ?? 'Not specified') }}</span>
        </div>
        @if($order->tracking_number)
        <div class="info-row">
            <span class="info-label">Tracking Number:</span>
            <span class="info-value">{{ $order->tracking_number }}</span>
        </div>
        @endif
        @if($order->transaction_id)
        <div class="info-row">
            <span class="info-label">Transaction ID:</span>
            <span class="info-value">{{ $order->transaction_id }}</span>
        </div>
        @endif
    </div>

    <div class="address-section">
        <div class="address-box">
            <h4>Shipping Address</h4>
            @php
                $shippingAddress = is_string($order->shipping_address) ? json_decode($order->shipping_address, true) : $order->shipping_address;
            @endphp
            @if($shippingAddress)
                @if(is_array($shippingAddress))
                    @foreach($shippingAddress as $key => $value)
                        @if($value)
                            <p><strong>{{ ucfirst(str_replace('_', ' ', $key)) }}:</strong> {{ $value }}</p>
                        @endif
                    @endforeach
                @else
                    <p>{{ $shippingAddress }}</p>
                @endif
            @else
                <p>No shipping address provided</p>
            @endif
        </div>
        <div class="address-box">
            <h4>Billing Address</h4>
            @php
                $billingAddress = is_string($order->billing_address) ? json_decode($order->billing_address, true) : $order->billing_address;
            @endphp
            @if($billingAddress)
                @if(is_array($billingAddress))
                    @foreach($billingAddress as $key => $value)
                        @if($value)
                            <p><strong>{{ ucfirst(str_replace('_', ' ', $key)) }}:</strong> {{ $value }}</p>
                        @endif
                    @endforeach
                @else
                    <p>{{ $billingAddress }}</p>
                @endif
            @else
                <p>Same as shipping address</p>
            @endif
        </div>
    </div>

    <div class="order-info">
        <h3>Shipping Method</h3>
        @php
            $shippingMethod = is_string($order->shipping_method) ? json_decode($order->shipping_method, true) : $order->shipping_method;
        @endphp
        @if($shippingMethod)
            @if(is_array($shippingMethod))
                @foreach($shippingMethod as $key => $value)
                    @if($value)
                        <div class="info-row">
                            <span class="info-label">{{ ucfirst(str_replace('_', ' ', $key)) }}:</span>
                            <span class="info-value">{{ $value }}</span>
                        </div>
                    @endif
                @endforeach
            @else
                <div class="info-row">
                    <span class="info-label">Method:</span>
                    <span class="info-value">{{ $shippingMethod }}</span>
                </div>
            @endif
        @endif
    </div>

    <div class="order-info">
        <h3>Customer Information</h3>
        @php
            $customer = null;
            if ($order->user_id) {
                $customer = \App\Models\User::find($order->user_id);
            }
        @endphp
        @if($customer)
            <div class="info-row">
                <span class="info-label">Name:</span>
                <span class="info-value">{{ $customer->name }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Email:</span>
                <span class="info-value">{{ $customer->email }}</span>
            </div>
        @endif
    </div>

    <div class="items-section">
        <h3>Order Items</h3>
        @php
            $items = $order->items ?? ($orderContent['items'] ?? []);
            $currency = $currency ?? '£';
            $subtotal = 0;
        @endphp
        @if($items && count($items) > 0)
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Quantity</th>
                        <th>Price</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $item)
                        @php
                            $product = null;
                            if (isset($item['itemable_type']) && isset($item['itemable_id'])) {
                                $productClass = $item['itemable_type'];
                                if (class_exists($productClass)) {
                                    $product = $productClass::find($item['itemable_id']);
                                }
                            }
                            $quantity = $item['quantity'] ?? 1;
                            $salePrice = $product->sale_price ?? null;
                            $regularPrice = $product->regular_price ?? ($item['regular_price'] ?? 0);
                            $price = $salePrice ?? $regularPrice;
                            $itemTotal = $price * $quantity;
                            $subtotal += $itemTotal;
                        @endphp
                        <tr>
                            <td>
                                @if($product)
                                    <strong>{{ $product->name }}</strong>
                                    @if($product->sku)
                                        <br><small>SKU: {{ $product->sku }}</small>
                                    @endif
                                @else
                                    {{ $item['name'] ?? ('Product ID: ' . ($item['itemable_id'] ?? 'Unknown')) }}
                                @endif
                            </td>
                            <td>{{ $quantity }}</td>
                            <td>
                                @if($salePrice)
                                    <span style="color: #007bff; font-weight: bold;">{{ $currency }}{{ number_format($salePrice, 2) }}</span>
                                    <br>
                                    <span style="text-decoration: line-through; color: #6c757d;">{{ $currency }}{{ number_format($regularPrice, 2) }}</span>
                                @else
                                    <span>{{ $currency }}{{ number_format($regularPrice, 2) }}</span>
                                @endif
                            </td>
                            <td>{{ $currency }}{{ number_format($itemTotal, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p>No items found in this order.</p>
        @endif
    </div>

    <div class="total-section">
        <h3>Order Summary</h3>
        @php
            $finalTotal = $order->total_amount ?? $subtotal;
            $discount = null;
            if ($order->discount) {
                $discount = is_string($order->discount) ? json_decode($order->discount, true) : $order->discount;
            }
            $discountAmount = 0;
            if ($discount && isset($discount['discount'])) {
                $discountAmount = $discount['discount'];
            }

            // Parse shipping fee from meta property
            $shippingFee = 0;
            $orderMeta = null;
            if ($order->meta) {
                $orderMeta = is_string($order->meta) ? json_decode($order->meta, true) : $order->meta;
                if (isset($orderMeta['price_modification']['minimum_shipping_fee'])) {
                    $shippingFee = (float) $orderMeta['price_modification']['minimum_shipping_fee'];
                }
            }
        @endphp
        <div class="total-row">
            <span>Subtotal:</span>
            <span>{{ $currency }}{{ number_format($subtotal, 2) }}</span>
        </div>
        @if($shippingFee > 0)
            <div class="total-row">
                <span>Shipping Fee:</span>
                <span>{{ $currency }}{{ number_format($shippingFee, 2) }}</span>
            </div>
        @endif
        @if($discountAmount > 0)
            <div class="total-row">
                <span>Discount
                    @if($discount && isset($discount['coupon_code']))
                        ({{ $discount['coupon_code'] }})
                    @endif
                :</span>
                <span>-{{ $currency }}{{ number_format($discountAmount, 2) }}</span>
            </div>
           <div class="total-row">
               <span>Amount after Discount:</span>
               <span>{{ $currency }}{{ number_format(($subtotal - $discountAmount), 2) }}</span>
           </div>
           @if($discount && isset($discount['total_amount']))
           <div class="total-row">
               <span>Final Total (from discount breakdown):</span>
               <span>{{ $currency }}{{ number_format($discount['total_amount'], 2) }}</span>
           </div>
           @endif
        @endif
        <div class="total-row total-final">
            <span>Total Amount:</span>
            <span>{{ $currency }}{{ number_format($finalTotal, 2) }}</span>
        </div>
    </div>

    <div class="footer">
        <p>Thank you for confirming this order! 🙏</p>
        <p>If you have any questions, please contact support.</p>
    </div>
</div>
