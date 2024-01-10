<?php 

require_once 'config.php'; 
 

include_once 'dbConnect.php'; 
 

require_once 'vendor/stripe/stripe-php/init.php'; 
 
 
\Stripe\Stripe::setApiKey(STRIPE_API_KEY);//  устанавливает API-ключ для взаимодействия с API Stripe.
 
 
$jsonStr = file_get_contents('php://input'); //считываетданныеизпотокаввода,
$jsonObj = json_decode($jsonStr); //преобразует полученные данные в объект JSON.
 
 
$userID = isset($_SESSION['loggedInUserID'])?$_SESSION['loggedInUserID']:0; //Затем проверяется, есть ли в сессии значение loggedInUserID, и если есть, то оно присваивается переменной или 0
 
if($jsonObj->request_type == 'create_customer_subscription'){ //проверяется тип запроса в объекте JSON. Если request_type равен 'create_customer_subscription', выполняются следующие действия:
    $subscr_plan_id = !empty($jsonObj->subscr_plan_id)?$jsonObj->subscr_plan_id:''; 
    $name = !empty($jsonObj->name)?$jsonObj->name:''; //Значения subscr_plan_id, name и email из объекта JSON присваиваются соответствующим переменным
    $email = !empty($jsonObj->email)?$jsonObj->email:''; 
     
   
    $sqlQ = "SELECT `name`,`price`,`interval`,`interval_count` FROM plans WHERE id=?"; //Выполняется SQL-запрос для получения данных о плане подписки из таблицы plans. Значение subscr_plan_id используется для получения соответствующей записи из таблицы.
    $stmt = $db->prepare($sqlQ); 
    $stmt->bind_param("i", $subscr_plan_id); 
    $stmt->execute(); 
    $stmt->bind_result($planName, $planPrice, $planInterval, $intervalCount); //Результаты запроса привязываются к соответствующим переменным 
    $stmt->fetch(); 
 
   
    $planPriceCents = round($planPrice*100); //тобыполучитьценувцентах
     
    
    try {   //Выполняется попытка создания нового клиента в Stripe с помощью метода \Stripe\Customer::create(). В качестве параметров передаются имя и электронная почта клиента.
        $customer = \Stripe\Customer::create([ 
            'name' => $name,  
            'email' => $email 
        ]);  
    }catch(Exception $e) {   
        $api_error = $e->getMessage();   //Если при создании клиента возникает исключение, ошибка сохраняется в переменную $api_error.
    } 
     
    if(empty($api_error) && $customer){ //Если переменная пустаиобъектcustomer существует, выполняются следующие действия:
       
        
        try { 
            
            $price = \Stripe\Price::create([ //Создается новая цена (price) с помощью метода В качестве параметров передаются цена в центах нтерваликоличествоинтервалов иназваниепродукта
                'unit_amount' => $planPriceCents, 
                'currency' => STRIPE_CURRENCY, 
                'recurring' => ['interval' => $planInterval, 'interval_count' => $intervalCount], 
                'product_data' => ['name' => $planName], 
            ]); 
        } catch (Exception $e) {  
            $api_error = $e->getMessage(); //Если при создании цены возникает исключение, ошибка сохраняется в переменную $api_error
        } 
         
        if(empty($api_error) && $price){ //Если переменная пустаиобъектprice существует, выполняются следующие действия:
           
            try { 
                $subscription = \Stripe\Subscription::create([ //Создается новая подписка (subscription) с помощью метода В качестве параметров передаются идентификатор клиента иэлементыподписки вкоторомуказываетсяидентификаторцены
                    'customer' => $customer->id, 
                    'items' => [[ 
                        'price' => $price->id, 
                    ]], 
                    'payment_behavior' => 'default_incomplete', //Устанавливается поведение платежа и сохраняются настройки платежа с сохранением метода оплаты по умолчанию для подписки
                    'payment_settings' => ['save_default_payment_method' => 'on_subscription'], 
                    'expand' => ['latest_invoice.payment_intent'], //Расширяются данные о последнем счете
                ]); 
            }catch(Exception $e) { 
                $api_error = $e->getMessage(); 
            } 
             
            if(empty($api_error) && $subscription){ //Если переменная пуста
                $output = [ //Создается выходной массив данны  преобразуется в формат JSON и выводится с помощью функции echo.
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
        $customer = \Stripe\Customer::retrieve($customer_id);// выполняется попытка получить данные о клиенте с помощью метода  
    }catch(Exception $e) {   
        $api_error = $e->getMessage(); //В случае возникновения исключения, ошибка сохраняется в переменную  
    } 
     
   
    if(!empty($payment_intent) && $payment_intent->status == 'succeeded'){ //непустаистатусплатежа и статус платежа равен аксес
        $payment_intent_id = $payment_intent->id; //Идентификатор платежа 
        $paidAmount = $payment_intent->amount; 
        $paidAmount = ($paidAmount/100); //делитсяна100,чтобыполучитьсуммувосновнойвалюте,иприсваиваетсяпеременной
        $paidCurrency = $payment_intent->currency;//Валюта платежа 
        $payment_status = $payment_intent->status; //Статус платежа
        $created = date("Y-m-d H:i:s", $payment_intent->created); 
 
       
        try {   
            $subscriptionData = \Stripe\Subscription::retrieve($subscription_id);  // попытка получить данные о подписке с помощью метода
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
         
       
        $sqlQ = "SELECT id FROM user_subscriptions WHERE stripe_payment_intent_id = ?"; 
        $stmt = $db->prepare($sqlQ);  
        $stmt->bind_param("s", $payment_intent_id); 
        $stmt->execute(); 
        $stmt->bind_result($id); 
        $stmt->fetch(); 
        $prevPaymentID = $id; 
        $stmt->close(); 
         
        $payment_id = 0; 
        if(!empty($prevPaymentID)){ // выполняется SQL-запрос для проверки наличия записи о предыдущем платеже с помощью идентификатора платежа
            $payment_id = $prevPaymentID; 
        }else{ //Если запись о предыдущем платеже не существует, выполняется SQL-запрос для добавления новой записи в таблицу user_subscriptions. Переданные параметры - идентификатор пользователя, идентификатор плана, идентификатор клиента Stripe
            
            $sqlQ = "INSERT INTO user_subscriptions (user_id,plan_id,stripe_customer_id,stripe_plan_price_id,stripe_payment_intent_id,stripe_subscription_id,default_payment_method,default_source,paid_amount,paid_amount_currency,plan_interval,plan_interval_count,customer_name,customer_email,plan_period_start,plan_period_end,created,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"; 
            $stmt = $db->prepare($sqlQ); 
            $stmt->bind_param("iissssssdssissssss", $userID, $subscr_plan_id, $customer_id, $plan_price_id, $payment_intent_id, $subscription_id, $default_payment_method, $default_source, $paidAmount, $paidCurrency, $plan_interval, $plan_interval_count, $customer_name, $customer_email, $current_period_start, $current_period_end, $created, $payment_status); 
            $insert = $stmt->execute(); 
             
            if($insert){ 
                $payment_id = $stmt->insert_id; 
                 
                
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