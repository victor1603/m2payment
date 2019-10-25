define(
    [
        'Magento_Payment/js/view/payment/cc-form',
        'jquery',
        'mage/url',
        'Magento_Customer/js/customer-data',
        'Magento_Checkout/js/model/totals',
        'Magento_Checkout/js/action/place-order',
        'mage/storage',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/url-builder',
        'Magento_Checkout/js/action/redirect-on-success',
        'ko',
        'mage/translate',
        'mage/url',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Payment/js/model/credit-card-validation/validator'
    ],
    function (
        Component, $, url, customerData, totals, placeOrder, storage, quoteII, urlBuilder, redirectOnSuccessAction,ko,$t
    ) {
        'use strict';

        return Component.extend({
            redirectAfterPlaceOrder: true,
            defaults: {
                template: {
                    name: 'CodeCustom_Payments/instant_installment/checkout',
                    afterRender: function (renderedNodesArray, data) {
                        let cart = customerData.get('cart')();
                        var dataLoaded = false;
                        if(cart.instant_installment.data) {
                            if(cart.instant_installment.data.ii_term && !cart.instant_installment.error) {
                                if(renderedNodesArray.length) {
                                    data.selectPaymentMethod();
                                    dataLoaded = true;
                                    jQuery('.payment-group-credit-container').each(function(){
                                        jQuery(this).removeClass('hide');
                                        jQuery(this).show();
                                    });
                                }
                            }
                        }
                        if (quoteII.paymentMethod()) {
                            let method = quoteII.paymentMethod().method;
                            if(
                                method === 'parts_payment'
                                ||
                                method === 'instant_installment'
                            ){
                                jQuery('.payment-group-credit-container').each(function(){
                                    jQuery(this).removeClass('hide');
                                    jQuery(this).show();
                                });
                            }
                        }
                        if (!dataLoaded) {
                            data.selectPaymentMethod();
                        }
                    }
                }
            },

            /**
             * Is method visible
             * @returns {boolean}
             */
            isVisible: function() {
                let cartData = customerData.get('cart')();
                return !cartData.instant_installment.error;
            },

            getErrorMessage: function() {
                let cart = customerData.get('cart')();
                return cart.instant_installment.error;
            },

            isCheckedPart: ko.computed(function () {
                function updateFieldsData() {
                    let term = parseInt($('.payment_quick_credit .payment-method-content .payment_count-info').html());
                    let price = parseInt($('.payment_quick_credit .payment-method-content .pricing-half-part .price').attr('part-price'));
                    $('[name="payment[ii_term]"]').val(term);
                    $('[name="payment[ii_price]"]').val(price);
                }

                if(quoteII.paymentMethod() && quoteII.paymentMethod().method === 'instant_installment'){
                    let cart = customerData.get('cart')();

                    var PP_CALCULATOR = (function () {
                        var my = {};
                        var commissions = {
                            ipCommission: 2.9,
                            acqCommission: 0.02,
                            ppCommission: 0.015
                        };
                        function privParseInt(num) {
                            return parseInt(num, 10)
                        }
                        function getValByTerm(term) {
                            var commissions = {
                                1: 0.015,
                                2: 0.025,
                                3: 0.045,
                                4: 0.07,
                                5: 0.09,
                                6: 0.115,
                                7: 0.135,
                                8: 0.155,
                                9: 0.165,
                                10: 0.17,
                                11: 0.175,
                                12: 0.19,
                                13: 0.205,
                                14: 0.22,
                                15: 0.235,
                                16: 0.245,
                                17: 0.26,
                                18: 0.27,
                                19: 0.285,
                                20: 0.295,
                                21: 0.31,
                                22: 0.32,
                                23: 0.33,
                                24: 0.345
                            };
                            return commissions[term];
                        }
                        function ipagetValByTerm(term) {
                            var ipacommissions = {
                                1: 0.015,
                                2: 0.025,
                                3: 0.045,
                                4: 0.064,
                                5: 0.076,
                                6: 0.082,
                                7: 0.087,
                                8: 0.097,
                                9: 0.106,
                                10: 0.116,
                                11: 0.122,
                                12: 0.125,
                                13: 0.128,
                                14: 0.131,
                                15: 0.134,
                                16: 0.137,
                                17: 0.14,
                                18: 0.143,
                                19: 0.147,
                                20: 0.155,
                                21: 0.162,
                                22: 0.17,
                                23: 0.176,
                                24: 0.183
                            };
                            return ipacommissions[term];
                        }
                        my.calculatePhys = function (paymentsCount, price) {
                            if (isNaN(paymentsCount) || isNaN(price)) return;
                            paymentsCount = privParseInt(paymentsCount) + 1;
                            price = privParseInt(price);
                            var ip = price / paymentsCount + price * (commissions.ipCommission / 100);
                            var pp = price / paymentsCount;
                            var ipa = (price / paymentsCount) + (price * 0.99 / 100);
                            return ({
                                payCount: paymentsCount,
                                ipValue: ip.toFixed(2),
                                ipaValue: ipa.toFixed(2),
                                ppValue: pp.toFixed(2)
                            });
                        };
                        my.calculateJur = function (paymentsCount, price) {
                            if (isNaN(paymentsCount) || isNaN(price)) return;
                            paymentsCount = privParseInt(paymentsCount) + 1;
                            price = privParseInt(price);
                            var tabVal = getValByTerm(paymentsCount - 1);
                            var stpp = price * (1 - (tabVal + commissions.acqCommission));
                            var ipaTabVal = ipagetValByTerm(paymentsCount - 1);
                            var ipa = price - (price * ipaTabVal + price * 0.02);
                            var pp = 0;
                            var ppValHint = '0.00';
                            var singlePayment = price / paymentsCount;
                            var ppFirst = singlePayment - price * (commissions.acqCommission + commissions.ppCommission);
                            var ppSecond = singlePayment;
                            var ppOther = (paymentsCount - 1) * ppSecond;
                            pp = ppFirst + ppOther;
                            ppValHint = ppFirst.toFixed(2) + " + " + (paymentsCount - 1) + "*" + ppSecond.toFixed(2);
                            var ip = price * (1 - commissions.acqCommission);
                            return ({
                                payCount: paymentsCount,
                                stPpValue: stpp.toFixed(2),
                                ipaValue: ipa.toFixed(2),
                                ipValue: ip.toFixed(2),
                                ppValue: pp.toFixed(2),
                                ppValueHint: ppValHint
                            });
                        };
                        return my;
                    }());

                    if(!cart.instant_installment.error){
                        function init_overpayment(){
                            let eltotal =  totals.totals().grand_total;
                            let montn =  $(".payment_quick_credit .select-desctop-ph").val();
                            let month_price = $('.payment_quick_credit .pricing-half-part .regular-price .price').attr('part-price');
                            let result = ((parseInt(montn) + 1) * parseInt(month_price)) - parseInt(eltotal);

                            $('.payment_quick_credit .overpayment-price').html($t("Overpayments") + " " + result + " " + $t("grn"));
                            $('[name="payment[ii_over_price]"]').val(result);
                        }

                        let cart = customerData.get('cart')();
                        var max_credit_terms = cart.instant_installment.data.credit_range;
                        var custom_values = [];
                        let customerTerm = cart.instant_installment.data.ii_term;
                        let startValue = customerTerm ? customerTerm : 0;
                        $.each(max_credit_terms, function(i) {
                            if(max_credit_terms[i] === startValue) {
                                startValue = i;
                            }
                            custom_values.push(max_credit_terms[i] -1 );
                        });
                        var decCache = [],
                            decCases = [2, 0, 1, 1, 1, 2];
                        function decOfNum(number, titles) {
                            if(!decCache[number]) decCache[number] = number % 100 > 4 && number % 100 < 20 ? 2 : decCases[Math.min(number % 10, 5)];
                            return titles[decCache[number]];
                        }


                        var total_product_price =  totals.totals().grand_total;
                        $('.payment_quick_credit [name="payment[part_payment_term]"]').val(custom_values[startValue] + 1);
                        var resCalc = PP_CALCULATOR.calculatePhys(custom_values[startValue], total_product_price);
                        $('.payment_quick_credit .pricing-half-part .regular-price .price').html(resCalc['ipValue']).attr('part-price',resCalc['ipValue']);
                        $('.payment_quick_credit [name="payment[part_payment_price]"]').val(resCalc['ipValue']);

                        $(".payment_quick_credit .payment_count-info").html(custom_values[startValue] + 1);


                        $.each(custom_values, function(i) {
                            $('.payment_quick_credit select.select-desctop-ph')
                                .append($('<option value="'+ custom_values[i] +'">'+ custom_values[i] + '</option>'));
                        });

                        $(".payment_quick_credit .select-desctop-ph").val(custom_values[startValue]);
                        $(".payment_quick_credit .select-desctop-ph").trigger('change');
                        $('.payment_quick_credit .payment_count-info + span').html(decOfNum(custom_values[startValue] + 1,[$t('payment'),$t('payments'),$t('of payments')]));
                        $(".payment_quick_credit .select-desctop-ph + span").html(decOfNum(custom_values[startValue],[$t('month'),$t('months'),$t('of months')]));
                        init_overpayment();
                        updateFieldsData();
                        $(document).on('change','.payment_quick_credit select.select-desctop-ph',function(){
                            var current_month = $(this).val();

                            var integer_month  = parseInt(current_month) - 1;
                            var translate_month = parseInt(current_month) + 1;
                            var current_month_int = parseInt(current_month);
                            var resCalc_change = PP_CALCULATOR.calculatePhys(current_month_int, total_product_price);
                            $(".payment_quick_credit .pricing-half-part .price-box .price").text( resCalc_change['ipValue']).attr('part-price',resCalc_change['ipValue']);

                            $(".payment_quick_credit .payment_count-info").html(parseInt(current_month) + 1);
                            $('.payment_quick_credit [name="payment[part_payment_term]"]').val(parseInt(current_month) + 1);
                            $('.payment_quick_credit [name="payment[part_payment_price]"]').val(resCalc_change['ipValue']);
                            $('.payment_quick_credit .payment_count-info + span').html(decOfNum(translate_month,[$t('payment'),$t('payments'),$t('of payments')]));
                            $(".payment_quick_credit .select-desctop-ph + span").html(decOfNum((parseInt(current_month)),[$t('month'),$t('months'),$t('of months')]));
                            init_overpayment();
                            updateFieldsData();
                        });
                        var code = {};
                        $(".payment_quick_credit  select option").each(function () {
                            if(code[this.text]) {
                                $(this).remove();
                            } else {
                                code[this.text] = this.value;
                            }
                        });
                        updateFieldsData();
                    }
                }
                return 1;

            }),

            /**
             * Method code
             * @returns {string}
             */
            getCode: function() {
                return 'instant_installment';
            },

            /**
             * Is Method active
             * @returns {boolean}
             */
            isActive: function() {
                return true;
            },

            /**
             * Instant payment data
             */
            getInstantPaymentData: function() {
                let cart = customerData.get('cart')();
                return cart.instant_installment.data;
            },

            /**
             * Incoming credit range
             * @returns {*}
             */
            getCreditRange: function() {
                let data = this.getInstantPaymentData();
                return data.credit_range;
            },

            /**
             * After place order
             */
            afterPlaceOrder: function () {
                $.post(url.build('payment/checkout/pbinstantinstallment'), {
                    'ii_term': $('[name="payment[ii_term]"]').val(),
                    'ii_price': $('[name="payment[ii_price]"]').val(),
                    'ii_over_price': $('[name="payment[ii_over_price]"]').val()
                }).done(function(data) {
                    if (!data.status) {
                        return
                    }
                    if (data.status == 'success') {
                        if (data.redirect) {
                            window.location = data.redirect;
                        }
                    } else {
                        if (data.redirect) {
                            window.location = data.redirect;
                        }
                    }
                });
            },

            /**
             * Place order.
             */
            placeOrder: function (data, event) {
                let self = this;
                if (event) {
                    event.preventDefault();
                    if(self.validateParams()) {

                        return true;
                    }
                }
                return false;
            },

            /**
             * Instant payment term data
             * @returns {boolean|*}
             */
            getInstantTermRange: function() {
                let cartData = customerData.get('cart')();
                return cartData.instant_installment.data.instant_range;
            },

            /**
             * Cart grand total
             */
            getGrandTotal: function () {
                return totals.totals().grand_total;
            },

            /**
             * Rest validation
             */
            validateParams: function() {
                let self = this;
                let paymentData = self.getData();
                let serviceUrl,payload;
                let quote_data = quoteII.getItems();
                let quote_id = quote_data[0].quote_id;
                let data = {
                    'payment_data' : JSON.stringify(paymentData),
                    'quote_id' : quote_id,
                    'range' : self.getCreditRange()
                };

                payload = {
                    payment: JSON.stringify(data),
                };
                serviceUrl = urlBuilder.createUrl('/pb/validation/validate', {});
                let result = false;
                storage.post(
                    serviceUrl,
                    JSON.stringify(payload)
                ).done(function (res) {
                    $('[data-role="loader"]').hide();
                    let decodedRes = JSON.parse(res);
                    result = decodedRes.success;
                    if(result) {
                        self.isPlaceOrderActionAllowed(false);

                        self.getPlaceOrderDeferredObject()
                            .fail(
                                function () {
                                    self.isPlaceOrderActionAllowed(true);
                                    let body = $('body').loader();
                                    body.loader('hide');
                                }
                            ).done(
                            function () {
                                self.afterPlaceOrder();
                                let body = $('body').loader();
                                // body.loader('hide');
                            }
                        );
                    }
                    // error - message decodedRes.message
                }).fail(function (res) {
                    result = false;
                    $('[data-role="loader"]').hide();
                });
            },

            getDefinedTerm: function() {
                return '1';
            },

            /**
             * @return {*}
             */
            getPlaceOrderDeferredObject: function () {
                return $.when(
                    placeOrder(this.getData(), this.messageContainer)
                );
            },

            /**
             * Get method data
             * @returns {{additional_data: {ii_term: (*|jQuery), ii_price: (*|jQuery)}, method: *}}
             */
            getData: function () {
                return {
                    'method': this.item.method,
                    'additional_data': {
                        'ii_term': $('[name="payment[ii_term]"]').val(),
                        'ii_price': $('[name="payment[ii_price]"]').val(),
                        'ii_over_price': $('[name="payment[ii_over_price]"]').val()
                    }
                };
            },
        });
    }
);