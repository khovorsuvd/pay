<?php 
// Include the configuration file 
require_once 'config.php'; 
 
// Include the database connection file 
include_once 'dbConnect.php'; 
 
// Include the Stripe PHP library 
require_once 'vendor/stripe/stripe-php/init.php'; 
 
// Set API key 
\Stripe\Stripe::setApiKey(STRIPE_API_KEY); 
 
// Retrieve JSON from POST body 
$jsonStr = file_get_contents('php://input'); 
$jsonObj = json_decode($jsonStr); 
 
// Get user ID from current SESSION 
$userID = isset($_SESSION['loggedInUserID'])?$_SESSION['loggedInUserID']:0; 
 
if($jsonObj->request_type == 'create_customer_subscription'){ 
    $subscr_plan_id = !empty($jsonObj->subscr_plan_id)?$jsonObj->subscr_plan_id:''; 
    $name = !empty($jsonObj->name)?$jsonObj->name:''; 
    $email = !empty($jsonObj->email)?$jsonObj->email:''; 
     
    // Fetch plan details from the database 
    $sqlQ = "SELECT `name`,`price`,`interval`,`interval_count` FROM plans WHERE id=?"; 
    $stmt = $db->prepare($sqlQ); 
    $stmt->bind_param("i", $subscr_plan_id); 
    $stmt->execute(); 
    $stmt->bind_result($planName, $planPrice, $planInterval, $intervalCount); 
    $stmt->fetch(); 
 
    // Convert price to cents 
    $planPriceCents = round($planPrice*100); 
     
    // Add customer to stripe 
    try {   
        $customer = \Stripe\Customer::create([ 
            'name' => $name,  
            'email' => $email 
        ]);  
    }catch(Exception $e) {   
        $api_error = $e->getMessage();   
    } 
     
    if(empty($api_error) && $customer){ 
        try { 
            // Create price with subscription info and interval 
            $price = \Stripe\Price::create([ 
                'unit_amount' => $planPriceCents, 
                'currency' => STRIPE_CURRENCY, 
                'recurring' => ['interval' => $planInterval, 'interval_count' => $intervalCount], 
                'product_data' => ['name' => $planName], 
            ]); 
        } catch (Exception $e) {  
            $api_error = $e->getMessage(); 
        } 
         
        if(empty($api_error) && $price){ 
            // Create a new subscription 
            try { 
                $subscription = \Stripe\Subscription::create([ 
                    'customer' => $customer->id, 
                    'items' => [[ 
                        'price' => $price->id, 
                    ]], 
                    'payment_behavior' => 'default_incomplete', 
                    'payment_settings' => ['save_default_payment_method' => 'on_subscription'], 
                    'expand' => ['latest_invoice.payment_intent'], 
                ]); 
            }catch(Exception $e) { 
                $api_error = $e->getMessage(); 
            } 
             
            if(empty($api_error) && $subscription){ 
                $output = [ 
                    'subscriptionId' => $subscription->id, 
                    'clientSecret' => $subscription->latest_invoice->payment_intent->client_secret, 
                    'customerId' => $customer->id 
                ]; 
             
                echo json_encode($output); 
            }else{ 
                echo json_encode(['error' => $api_error]); 
            } 
        }else{ 
            echo json_encode(['error' => $api_error]); 
        } 
    }else{ 
        echo json_encode(['error' => $api_error]); 
    } 
}elseif($jsonObj->request_type == 'payment_insert'){ 
    $payment_intent = !empty($jsonObj->payment_intent)?$jsonObj->payment_intent:''; 
    $subscription_id = !empty($jsonObj->subscription_id)?$jsonObj->subscription_id:''; 
    $customer_id = !empty($jsonObj->customer_id)?$jsonObj->customer_id:''; 
    $subscr_plan_id = !empty($jsonObj->subscr_plan_id)?$jsonObj->subscr_plan_id:''; 
 
    // Retrieve customer info 
    try {   
        $customer = \Stripe\Customer::retrieve($customer_id);  
    }catch(Exception $e) {   
        $api_error = $e->getMessage();   
    } 
     
    // Check whether the charge was successful 
    if(!empty($payment_intent) && $payment_intent->status == 'succeeded'){ 
        $payment_intent_id = $payment_intent->id; 
        $paidAmount = $payment_intent->amount; 
        $paidAmount = ($paidAmount/100); 
        $paidCurrency = $payment_intent->currency; 
        $payment_status = $payment_intent->status; 
        $created = date("Y-m-d H:i:s", $payment_intent->created); 
 
        // Retrieve subscription info 
        try {   
            $subscriptionData = \Stripe\Subscription::retrieve($subscription_id);  
        }catch(Exception $e) {   
            $api_error = $e->getMessage();   
        } 
 
        $default_payment_method = $subscriptionData->default_payment_method; 
        $default_source = $subscriptionData->default_source; 
        $plan_obj = $subscriptionData->plan; 
        $plan_price_id = $plan_obj->id; 
        $plan_interval = $plan_obj->interval; 
        $plan_interval_count = $plan_obj->interval_count; 
 
        $current_period_start = $current_period_end = ''; 
        if(!empty($subscriptionData)){ 
            $created = date("Y-m-d H:i:s", $subscriptionData->created); 
            $current_period_start = date("Y-m-d H:i:s", $subscriptionData->current_period_start); 
            $current_period_end = date("Y-m-d H:i:s", $subscriptionData->current_period_end); 
        } 
         
        $customer_name = $customer_email = ''; 
        if(!empty($customer)){ 
            $customer_name = !empty($customer->name)?$customer->name:''; 
            $customer_email = !empty($customer->email)?$customer->email:''; 
 
            if(!empty($customer_name)){ 
                $name_arr = explode(' ', $customer_name); 
                $first_name = !empty($name_arr[0])?$name_arr[0]:''; 
                $last_name = !empty($name_arr[1])?$name_arr[1]:''; 
            } 
             
            // Insert user details if not exists in the DB users table 
            if(empty($userID)){ 
                $sqlQ = "INSERT INTO users (first_name,last_name,email) VALUES (?,?,?)"; 
                $stmt = $db->prepare($sqlQ); 
                $stmt->bind_param("sss", $first_name, $last_name, $customer_email); 
                $insertUser = $stmt->execute(); 
                 
                if($insertUser){ 
                    $userID = $stmt->insert_id; 
                } 
            } 
        } 
         
        // Check if any transaction data exists already with the same TXN ID 
        $sqlQ = "SELECT id FROM user_subscriptions WHERE stripe_payment_intent_id = ?"; 
        $stmt = $db->prepare($sqlQ);  
        $stmt->bind_param("s", $payment_intent_id); 
        $stmt->execute(); 
        $stmt->bind_result($id); 
        $stmt->fetch(); 
        $prevPaymentID = $id; 
        $stmt->close(); 
         
        $payment_id = 0; 
        if(!empty($prevPaymentID)){ 
            $payment_id = $prevPaymentID; 
        }else{ 
            // Insert transaction data into the database 
            $sqlQ = "INSERT INTO user_subscriptions (user_id,plan_id,stripe_customer_id,stripe_plan_price_id,stripe_payment_intent_id,stripe_subscription_id,default_payment_method,default_source,paid_amount,paid_amount_currency,plan_interval,plan_interval_count,customer_name,customer_email,plan_period_start,plan_period_end,created,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"; 
            $stmt = $db->prepare($sqlQ); 
            $stmt->bind_param("iissssssdssissssss", $userID, $subscr_plan_id, $customer_id, $plan_price_id, $payment_intent_id, $subscription_id, $default_payment_method, $default_source, $paidAmount, $paidCurrency, $plan_interval, $plan_interval_count, $customer_name, $customer_email, $current_period_start, $current_period_end, $created, $payment_status); 
            $insert = $stmt->execute(); 
             
            if($insert){ 
                $payment_id = $stmt->insert_id; 
                 
                // Update subscription ID in users table 
                $sqlQ = "UPDATE users SET subscription_id=? WHERE id=?"; 
                $stmt = $db->prepare($sqlQ); 
                $stmt->bind_param("ii", $payment_id, $userID); 
                $update = $stmt->execute(); 
            } 
        } 
         
        $output = [ 
            'payment_id' => base64_encode($payment_id) 
        ]; 
        echo json_encode($output); 
    }else{ 
        echo json_encode(['error' => 'Transaction has been failed!']); 
    } 
} 
?>