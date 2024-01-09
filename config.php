<?php 
/* Stripe API configuration 
 * Remember to switch to your live publishable and secret key in production! 
 * See your keys here: https://dashboard.stripe.com/account/apikeys 
 */ 
define('STRIPE_API_KEY', 'sk_test_51OUqNlDxIChVdQml1po5HPkMtyQaJdlwCHg3UBUr5Hf2sF48WCsjieeXTW7OpJuJYZUzjNwIBSnS7VcTQ6dWxQ3v00xDpjPVPx'); 
define('STRIPE_PUBLISHABLE_KEY', 'pk_test_51OUqNlDxIChVdQmlAVcetNtPnloBfFvEekPrFcoN3dP8VwScNOkAlnuZHhtDE8AKlGyBpsjAGRL5KgXuUUDpucTy005ek4Xyzt'); 
define('STRIPE_CURRENCY', 'USD'); 
  
// Database configuration  
define('DB_HOST', 'localhost:3307'); 
define('DB_USERNAME', 'root'); 
define('DB_PASSWORD', '1111'); 
define('DB_NAME', 'stripe_pay');
?>