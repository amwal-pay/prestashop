<section>
    <script type="text/javascript" src="{$url|escape:'htmlall':'UTF-8'}" ></script>
    <script type="text/javascript">
        const jsonData = {$jsonData nofilter}; // 'nofilter' allows raw JS object
        callSmartBox(jsonData);
        function callSmartBox(data) {

            if (!data["MID"] || !data["TID"]) {
                alert('Please ensure you are entering merchant , terminal keys');
                return;
            }

            SmartBox.Checkout.configure = {
                ...data,
                completeCallback: function (data) {
                    var dateResponse = data.data.data;
                    window.location = '{$callback_url}' +
                        '?amount=' + dateResponse.amount +
                        '&currencyId=' + dateResponse.currencyId +
                        '&customerId=' + dateResponse.customerId +
                        '&customerTokenId=' + dateResponse.customerTokenId +
                        '&merchantReference=' + dateResponse.merchantReference +
                        '&responseCode=' + data.data.responseCode +
                        '&transactionId=' + dateResponse.transactionId +
                        '&transactionTime=' + dateResponse.transactionTime +
                        '&secureHashValue=' + dateResponse.secureHashValue;
                },
                errorCallback: function (data) {
                    console.log("errorCallback Received Data", data);
                },
                cancelCallback: function () {
                    window.location = '{$base_url|escape:'htmlall':'UTF-8'}';
                },
                };

                SmartBox.Checkout.showSmartBox();
            }
    </script>

</section>