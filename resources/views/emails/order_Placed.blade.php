<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - Order Confirmation</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f8f8;
            padding: 20px;
        }

        .container {
            max-width: 600px;
            background-color: #ffffff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            margin: auto;
        }

        .header {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 2px solid #ddd;
        }

        .header img {
            max-width: 150px;
        }

        .invoice-info {
            text-align: right;
            margin-bottom: 20px;
        }

        .order-details,
        .summary-table {
            width: 100%;
            border-collapse: collapse;
        }

        .order-details td,
        .order-details th,
        .summary-table td,
        .summary-table th {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }

        .summary-table th {
            background-color: #f1f1f1;
        }

        .footer {
            text-align: center;
            font-size: 12px;
            color: #777;
            margin-top: 20px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <img src="https://raw.githubusercontent.com/Nakib00/zan-tech-invoice/refs/heads/main/ZAN%20Tech%20Logo.png"
                alt="ZAN Tech Logo">
            <h2>Invoice</h2>
        </div>
        <div class="invoice-info">
            <p><strong>Invoice Code:</strong> {{ $orderDetails->order->invoice_code }}</p>
            <p><strong>Date:</strong> {{ date('F d, Y') }}</p>
        </div>
        <table class="order-details">
            <tr>
                <td><strong>Billed To:</strong></td>
                <td>{{ $orderDetails->user->name }}<br>{{ $orderDetails->user->email }}<br>{{ $orderDetails->shipping_address->address }}
                </td>
            </tr>
        </table>
        <h3>Order Summary</h3>
        <table class="summary-table">
            <tr>
                <th>Item</th>
                <th>Quantity</th>
                <th>Price</th>
                <th>Total</th>
            </tr>
            @foreach ($orderDetails->order_items as $item)
            <tr>
                <td>{{ $item->name }}</td>
                <td>{{ $item->quantity }}</td>
                <td>Tk{{ number_format($item->price, 2) }}</td>
                <td>Tk{{ number_format($item->quantity * $item->price, 2) }}</td>
            </tr>
            @endforeach
            <tr>
                <td colspan="3" align="right"><strong>Subtotal of Items:</strong></td>
                <td>Tk{{ number_format($orderDetails->order->item_subtotal, 2) }}</td>
            </tr>
            <tr>
                <td colspan="3" align="right"><strong>Shipping:</strong></td>
                <td>Tk{{ number_format($orderDetails->order->shipping_charge, 2) }}</td>
            </tr>
            <tr>
                <td colspan="3" align="right"><strong>Discount:</strong></td>
                <td>Tk{{ number_format($orderDetails->order->discount, 2) }}</td>
            </tr>
            @if (isset($orderDetails->coupon))
            <tr>
                <td colspan="3" align="right"><strong>Coupon: {{ $orderDetails->coupon->code }}</strong></td>
                <td>Tk{{ number_format($orderDetails->coupon->amount, 2) }}</td>
            </tr>
            @endif
            <tr>
                <td colspan="3" align="right"><strong>Total:</strong></td>
                <td><strong>Tk{{ number_format($orderDetails->order->total_amount, 2) }}</strong></td>
            </tr>
        </table>
        <p>Thank you for your purchase!</p>
        <div class="footer">
            <p>&copy; {{ date('Y') }} ZAN Tech. All rights reserved.</p>
            <p>For support, contact us at
                <a href="mailto:zantechbd@gmail.com">zantechbd@gmail.com</a>
                or call <a href="tel:+8801894634149">+880 1894 634 149</a>
            </p>
        </div>
    </div>
</body>

</html>