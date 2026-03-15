# Midtrans Payment API Flow

This document describes the flow for frontend developers to implement Midtrans payments in the AlphaNext application.

## 1. Fetch Midtrans Configuration (Public)

Before initiating payment, the frontend can fetch the required `client_key` and environment status.

- **Endpoint**: `GET /api/public/midtrans/config`
- **Authentication**: Not Required
- **Response**:
```json
{
  "status": "success",
  "data": {
    "client_key": "SB-Mid-client-...",
    "is_production": false
  }
}
```

## 2. Create an Order

First, the frontend must create an order by sending the product ID and any applicable promo code.

- **Endpoint**: `POST /api/orders`
- **Authentication**: Required (Sanctum)
- **Payload**:
```json
{
  "product_id": 1,
  "promo_code": "DISCOUNT20" // Optional
}
```
- **Response**:
```json
{
  "status": "success",
  "data": {
    "id": 123,
    "merchant_order_id": "ORD-20260220-ABCDE",
    "amount": 100000,
    "status": "pending",
    "expires_at": "2026-02-20T01:15:00.000000Z"
  }
}
```

## 3. Initiate Payment

Once the order is created, use the order ID to get the Midtrans Snap Token.

- **Endpoint**: `POST /api/orders/{order_id}/pay`
- **Authentication**: Required (Sanctum)
- **Response**:
```json
{
  "status": "success",
  "data": {
    "token": "snap-token-xyz-123",
    "redirect_url": "https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-token-xyz-123"
  }
}
```

## 4. Frontend Integration (Snap JS)

Use the token retrieved above with the Midtrans Snap JS library.

### Load Snap JS
```html
<!-- For Sandbox -->
<script src="https://app.sandbox.midtrans.com/snap/snap.js" data-client-key="YOUR_CLIENT_KEY"></script>
<!-- For Production -->
<script src="https://app.sandbox.midtrans.com/snap/snap.js" data-client-key="YOUR_CLIENT_KEY"></script>
```

### Trigger Popup
```javascript
window.snap.pay('SNAP_TOKEN_FROM_BACKEND', {
  onSuccess: function(result) {
    /* Handle success - e.g., redirect to success page */
    console.log('success', result);
    window.location.href = '/payment-success?order_id=' + result.order_id;
  },
  onPending: function(result) {
    /* Handle pending - e.g., show instruction */
    console.log('pending', result);
    window.location.href = '/payment-pending?order_id=' + result.order_id;
  },
  onError: function(result) {
    /* Handle error */
    console.log('error', result);
  },
  onClose: function() {
    /* Handle when popup is closed without finishing payment */
    console.log('customer closed the popup without finishing the payment');
  }
});
```

## 5. Payment Verification (Optional for Frontend)

The backend handles the webhook notification from Midtrans automatically. However, the frontend can poll the order status if needed.

- **Endpoint**: `GET /api/orders/{order_id}`
- **Response Status Check**: `data.status` (pending, paid, cancelled, failed)

---

## Admin: Configuring Midtrans

Admin can change the Midtrans settings (Server Key, Client Key, Expiry, etc.) via:

- **Get Settings**: `GET /api/admin/midtrans-settings`
- **Update Settings**: `POST /api/admin/midtrans-settings`
  - Payload:
    ```json
    {
      "server_key": "SB-Mid-server-...",
      "client_key": "SB-Mid-client-...",
      "is_production": false,
      "merchant_name": "AlphaNext",
      "expiry_duration": 15,
      "expiry_unit": "minutes"
    }
    ```
