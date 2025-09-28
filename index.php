<?php
// Configurations
$config = [
    'ZENOPAY_API_KEY' => 'DtxN1A89j3uqrl4I4efP0ieRH6YpnXMhCu2ehxrdBWUaqT1FCve3taocadtPWN1ui3MpWdZ0vDYo927IvorEuw',
    'SUCCESS_URL' => 'http://localhost:8000/success.php'
];

// Handle AJAX payment request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'pay') {
    header('Content-Type: application/json');
    
    try {
        $phoneNumber = $_POST['phoneNumber'] ?? '';
        $amount = (int)($_POST['amount'] ?? 1000);
        
        if (empty($phoneNumber)) {
            echo json_encode(['success' => false, 'message' => 'Phone number is required']);
            exit;
        }

        if (!preg_match('/^0[67][0-9]{8}$/', $phoneNumber)) {
            echo json_encode(['success' => false, 'message' => 'Please enter a valid 10-digit phone number (06XXXXXXXX or 07XXXXXXXX)']);
            exit;
        }

        if ($amount < 1000) {
            echo json_encode(['success' => false, 'message' => 'Kiasi cha malipo lazima kiwe kuanzia Tsh 1000 au zaidi']);
            exit;
        }

        $orderId = 'PAY_' . time() . '_' . substr(md5(rand()), 0, 8);
        
        $payload = [
            'order_id' => $orderId,
            'buyer_email' => 'donor@example.com',
            'buyer_name' => 'Birthday Donor',
            'buyer_phone' => $phoneNumber,
            'amount' => $amount,
            'webhook_url' => $config['SUCCESS_URL']
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://zenoapi.com/api/payments/mobile_money_tanzania');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'x-api-key: ' . $config['ZENOPAY_API_KEY']
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            echo json_encode(['success' => false, 'message' => 'Network error: ' . $error]);
            exit;
        }
        
        $responseData = json_decode($response, true);
        
        if ($httpCode === 200 && isset($responseData['status']) && $responseData['status'] === 'success') {
            echo json_encode([
                'success' => true,
                'message' => $responseData['message'] ?? 'USSD push sent successfully. Please check your phone.',
                'order_id' => $responseData['order_id'] ?? $orderId
            ]);
        } else {
            $errorMessage = 'Payment failed';
            if ($httpCode === 403) {
                $errorMessage = 'Invalid API Key. Please verify your API credentials.';
            } elseif (isset($responseData['message'])) {
                $errorMessage = $responseData['message'];
            } elseif (isset($responseData['detail'])) {
                $errorMessage = $responseData['detail'];
            } elseif (isset($responseData['error'])) {
                $errorMessage = $responseData['error'];
            }
            
            echo json_encode(['success' => false, 'message' => $errorMessage]);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="sw">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Birthday Donation</title>
  <style>
    body,html {
      margin:0;
      padding:0;
      font-family:'Segoe UI', Tahoma, sans-serif;
      background:#f7f7f7;
      height:100%;
      overflow-x:hidden;
    }
    .image-container {
      position:relative;
      width:100%;
      height:100vh;
      overflow:hidden;
    }
    .fullscreen-image {
      width:100%;
      height:100%;
      object-fit:cover;
    }
    .overlay {
      position:absolute;
      top:0;left:0;
      width:100%;height:100%;
      background:rgba(0,0,0,0.4);
      display:flex;
      flex-direction:column;
      justify-content:center;
      align-items:center;
      text-align:center;
      color:#fff;
      padding:20px;
    }
    .overlay h1 {
      font-size:2rem;
      margin-bottom:10px;
    }
    .overlay p {
      font-size:1rem;
      max-width:400px;
    }
    .download-btn {
      position:absolute;
      top:15px;
      right:15px;
      padding:12px 20px;
      border:none;
      border-radius:30px;
      background:#ff4081;
      color:#fff;
      font-weight:bold;
      font-size:1rem;
      cursor:pointer;
      display:flex;
      align-items:center;
      gap:8px;
      box-shadow:0 4px 10px rgba(0,0,0,0.3);
    }
    .download-btn:hover {background:#e91e63;}
    /* Modal styles */
    .modal {
      display:none;
      position:fixed;
      z-index:1000;
      left:0;top:0;
      width:100%;height:100%;
      overflow:auto;
      background:rgba(0,0,0,0.6);
    }
    .modal-content {
      background:#fff;
      margin:10% auto;
      padding:20px;
      border-radius:12px;
      max-width:400px;
    }
    .close {
      float:right;
      font-size:28px;
      cursor:pointer;
    }
    .form-group {margin-bottom:15px;text-align:left;}
    .form-group input {
      width:100%;padding:12px;
      border:2px solid #ddd;
      border-radius:8px;
      font-size:1rem;
    }
    .pay-btn {
      width:100%;
      padding:12px;
      background:#4CAF50;
      color:#fff;
      border:none;
      border-radius:8px;
      font-size:1rem;
      cursor:pointer;
    }
    .pay-btn:disabled {background:#999;}
    .message-container {
      position:fixed;
      bottom:20px;
      left:50%;
      transform:translateX(-50%);
      z-index:2000;
    }
    .message {
      margin-top:10px;
      padding:12px 18px;
      border-radius:6px;
      color:#fff;
      font-weight:bold;
      animation:fadein 0.5s, fadeout 0.5s 4.5s;
    }
    .message.success {background:#4CAF50;}
    .message.error {background:#f44336;}
    @keyframes fadein {from{opacity:0;} to{opacity:1;}}
    @keyframes fadeout {from{opacity:1;} to{opacity:0;}}
  </style>
</head>
<body>
  <!-- Full-screen birthday image with text -->
  <div class="image-container">
    <img src="https://images.unsplash.com/photo-1604014237744-df89d6bde8d2?q=80&w=1200&auto=format&fit=crop"
         alt="Birthday Celebration" class="fullscreen-image">
    <div class="overlay">
      <h1>GUSA HAPO JUU</h1>
      <p>Asante kwa zawadi zako! Changia chochote kuanzia Tsh 1000 au zaidi ili kusherekea siku yangu ya kuzaliwa.</p>
    </div>
    <!-- Top right donate button -->
    <button id="downloadBtn" class="download-btn">
      <span class="btn-icon">🎁</span>
      Gusa hapa kuchangia
    </button>
  </div>

  <!-- Payment Modal -->
  <div id="paymentModal" class="modal">
    <div class="modal-content">
      <span class="close">&times;</span>
      <h2>Changia Birthday</h2>
      <form id="paymentForm">
        <div class="form-group">
          <label for="phoneNumber">Namba ya simu (M-Pesa/TigoPesa/Airtel Money Na Halotel)</label>
          <input type="tel" id="phoneNumber" name="phoneNumber" placeholder="07XXXXXXXX" required>
        </div>
        <div class="form-group">
          <label for="amount">Kiasi cha kuchangia (Tsh)</label>
          <input type="number" id="amount" name="amount" min="1000" placeholder="Weka kiasi kuanzia 1000" required>
        </div>
        <button type="submit" class="pay-btn" id="payBtn">
          <span class="btn-text">Lipia</span>
        </button>
      </form>
    </div>
  </div>

  <!-- Messages -->
  <div id="messageContainer" class="message-container"></div>

  <script>
    const paymentModal = document.getElementById('paymentModal');
    const donateBtn = document.getElementById('downloadBtn');
    const closeBtn = document.querySelector('.close');
    const paymentForm = document.getElementById('paymentForm');
    const payBtn = document.getElementById('payBtn');
    const phoneInput = document.getElementById('phoneNumber');
    const amountInput = document.getElementById('amount');
    const messageContainer = document.getElementById('messageContainer');

    donateBtn.addEventListener('click', showModal);
    closeBtn.addEventListener('click', hideModal);
    paymentForm.addEventListener('submit', handlePayment);
    window.addEventListener('click', (event) => { if (event.target === paymentModal) hideModal(); });

    function showModal(){paymentModal.style.display='block';document.body.style.overflow='hidden';}
    function hideModal(){paymentModal.style.display='none';document.body.style.overflow='auto';paymentForm.reset();}

    async function handlePayment(event) {
      event.preventDefault();
      const phoneNumber = phoneInput.value.trim();
      const amount = parseInt(amountInput.value.trim());
      if (!/^0[67][0-9]{8}$/.test(phoneNumber)) {showMessage('Ingiza namba sahihi ya simu (07XXXXXXXX)', 'error');return;}
      if (isNaN(amount) || amount < 1000) {showMessage('Kiasi lazima kiwe kuanzia Tsh 1000 au zaidi', 'error');return;}
      setPayButtonState(true);
      try {
        const formData = new FormData();
        formData.append('action','pay');
        formData.append('phoneNumber',phoneNumber);
        formData.append('amount',amount);
        const response = await fetch('',{method:'POST',body:formData});
        const result = await response.json();
        if(result.success){
          showMessage(result.message,'success');
          hideModal();
        } else {
          showMessage(result.message||'Payment failed.','error');
          setPayButtonState(false);
        }
      } catch(e){showMessage('Network error.','error');setPayButtonState(false);}
    }

    function setPayButtonState(loading){
      payBtn.disabled=loading;
      payBtn.querySelector('.btn-text').textContent=loading?'Inasubiri...':'Lipia';
    }
    function showMessage(text,type){
      const msg=document.createElement('div');
      msg.className=`message ${type}`;
      msg.textContent=text;
      messageContainer.appendChild(msg);
      setTimeout(()=>{if(msg.parentNode)msg.remove();},5000);
    }
  </script>
</body>
</html>