
let STRIPE_PUBLISHABLE_KEY = document.currentScript.getAttribute('STRIPE_PUBLISHABLE_KEY');


const stripe = Stripe(STRIPE_PUBLISHABLE_KEY);


const subscrFrm = document.querySelector("#subscrFrm");


subscrFrm.addEventListener("submit", handleSubscrSubmit);

let elements = stripe.elements();
var style = {
    base: {
        lineHeight: "30px",
        fontSize: "16px",
        border: "1px solid #ced4da",
    }
};
let cardElement = elements.create('card', { style: style });
cardElement.mount('#card-element');

cardElement.on('change', function (event) {
    displayError(event);
});

function displayError(event) {
    if (event.error) {
        showMessage(event.error.message);
    }
}

async function handleSubscrSubmit(e) {
    e.preventDefault();
    setLoading(true);
    
    let subscr_plan_id = document.getElementById("subscr_plan").value;
    let customer_name = document.getElementById("name").value;
    let customer_email = document.getElementById("email").value;
    
    
    fetch("payment_init.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ request_type:'create_customer_subscription', subscr_plan_id: subscr_plan_id, name: customer_name, email: customer_email }),
    })
    .then(response => response.json())
    .then(data => {
        if (data.subscriptionId && data.clientSecret) {
            paymentProcess(data.subscriptionId, data.clientSecret, data.customerId);
        } else {
            showMessage(data.error);
        }
        
        setLoading(false);
    })
    .catch(console.error);
}

function paymentProcess(subscriptionId, clientSecret, customerId){
    let subscr_plan_id = document.getElementById("subscr_plan").value;
    let customer_name = document.getElementById("name").value;
    
    // Create payment method and confirm payment intent.
    stripe.confirmCardPayment(clientSecret, {
        payment_method: {
            card: cardElement,
            billing_details: {
                name: customer_name,
            },
        }
    }).then((result) => {
        if(result.error) {
            showMessage(result.error.message);
            setLoading(false);
        } else {
            
            fetch("payment_init.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ request_type:'payment_insert', subscription_id: subscriptionId, customer_id: customerId, subscr_plan_id: subscr_plan_id,payment_intent: result.paymentIntent }),
            })
            .then(response => response.json())
            .then(data => {
                if (data.payment_id) {
                    window.location.href = 'payment-status.php?sid='+data.payment_id;
                } else {
                    showMessage(data.error);
                    setLoading(false);
                }
            })
            .catch(console.error);
        }
    });
}


function showMessage(messageText) {
    const messageContainer = document.querySelector("#paymentResponse");
    
    messageContainer.classList.remove("hidden");
    messageContainer.textContent = messageText;
    
    setTimeout(function () {
        messageContainer.classList.add("hidden");
        messageText.textContent = "";
    }, 5000);
}


function setLoading(isLoading) {
    if (isLoading) {
       
        document.querySelector("#submitBtn").disabled = true;
        document.querySelector("#spinner").classList.remove("hidden");
        document.querySelector("#buttonText").classList.add("hidden");
    } else {
        
        document.querySelector("#submitBtn").disabled = false;
        document.querySelector("#spinner").classList.add("hidden");
        document.querySelector("#buttonText").classList.remove("hidden");
    }
}