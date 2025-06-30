jQuery(function ($) {
    // Parse JSON string to JavaScript object
    var data = JSON.parse(sm.jsonData);
    callSmartBox(data);

    function callSmartBox(data) {
        if (data["MID"] === "" || data["TID"] === "") {
            alert('Please ensure you are entering merchant , terminal keys');
            return;
        }

        SmartBox.Checkout.configure = {
            ...data,

            completeCallback: function (data) {

                var dateResponse = data.data.data;
                window.location = sm.callback + '&amount=' + dateResponse.amount + '&currencyId=' + dateResponse.currencyId + '&customerId=' + dateResponse.customerId + '&customerTokenId=' + dateResponse.customerTokenId + '&merchantReference=' + dateResponse.merchantReference + '&responseCode=' + data.data.responseCode + '&transactionId=' + dateResponse.transactionId + '&transactionTime=' + dateResponse.transactionTime + '&secureHashValue=' + dateResponse.secureHashValue;
            },
            errorCallback: function (data) {
                console.log(data);
            },
            cancelCallback: function () {
                window.location = sm.cancel_url;
            },
        };

        SmartBox.Checkout.showSmartBox();
    }
});