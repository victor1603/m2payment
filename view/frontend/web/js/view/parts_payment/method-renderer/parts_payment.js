define(
    [
        'Magento_Payment/js/view/payment/cc-form',
        'jquery',
        'mage/url',
        'Magento_Checkout/js/model/quote',
        'ko',
        'Magento_Catalog/js/price-utils',
        'Magento_Checkout/js/model/totals',
        'Magento_Customer/js/customer-data',
        'mage/translate',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Payment/js/model/credit-card-validation/validator',
        'jquery/ui',
        'accordion'
    ],
    function (Component, $, url, quote, ko, priceUtils, totals, customerData, $t) {
        'use strict';
        var init = false;

        return Component.extend({
            redirectAfterPlaceOrder: false,
            defaults: {
                template: {
                    name: 'CodeCustom_Payments/parts_payment/checkout',
                    afterRender: function (renderedNodesArray, data) {
                        let cart = customerData.get('cart')();
                        var dataLoaded = false;
                        if(cart.has_part_payment) {
                            let termData = JSON.parse(cart.pp_term);
                            if(termData.user_defined && !cart.part_payment_message) {
                                if(renderedNodesArray.length) {
                                    data.selectPaymentMethod();
                                    dataLoaded = true;
                                    jQuery(this).removeClass('hide');
                                    jQuery(this).show();
                                }
                            }
                            if (quote.paymentMethod()) {
                                let method = quote.paymentMethod().method;
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
                        }
                        if (!dataLoaded) {
                            data.selectPaymentMethod();
                        }
                    }
                }
            },
            isCheckedPart: ko.computed(function () {
                if(quote.paymentMethod() && quote.paymentMethod().method === 'parts_payment'){
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

                    let cart = customerData.get('cart')();
                    var self = this;
                    if(cart.has_part_payment){
                        let termData = JSON.parse(cart.pp_term);
                        var max_credit_terms = termData.pp_term_range;
                        var custom_values = [];
                        let startValue = termData.user_defined ? termData.user_defined : 0;
                        $.each(max_credit_terms, function(i) {
                            if(max_credit_terms[i] === startValue) {
                                startValue = i;
                            }
                            custom_values.push(max_credit_terms[i] -1 );
                        });
                        var total_product_price =  totals.totals().grand_total;
                        var resCalc = PP_CALCULATOR.calculatePhys(custom_values[startValue], total_product_price);
                        $(".half-part .payment_count-info").html(custom_values[startValue] + 1);
                        $(".half-part .month_count-info").html(custom_values[startValue]);
                        $('.half-part [name="payment[pp_term]"]').val(custom_values[startValue] + 1);
                        $('.half-part [name="payment[pp_price]"]').val(resCalc['ppValue']);
                        // load page part-half payment result price
                        $(".half-part .item-credit .price-box .price").text( resCalc['ppValue'] );
                        $('.half-part [name="payment[pp_price]"]').val(resCalc['ppValue']);
                        var decCache = [],
                            decCases = [2, 0, 1, 1, 1, 2];
                        function decOfNum(number, titles)
                        {
                            if(!decCache[number]) decCache[number] = number % 100 > 4 && number % 100 < 20 ? 2 : decCases[Math.min(number % 10, 5)];
                            return titles[decCache[number]];
                        }
                        $('.half-part .payment_count-info + span').html(decOfNum(custom_values[startValue] + 1,[$t('payment'),$t('payments'),$t('of payments')]));
                        $(".half-part .month_count-info + span").html(decOfNum(custom_values[startValue],[$t('month'),$t('months'),$t('of months')]));
                        $.each(custom_values, function(i) {
                            $('.half-part .option-progres-bar')
                                .append($('<option value="'+ custom_values[i] +'">' + custom_values[i] + '</option>'));
                        });

                        $('.half-part .option-progres-bar option:first-child').attr("selected", "selected");
                        $('.half-part .option-progres-bar').trigger('change');
                        $(document).on('click touchstart','.half-part .mobile-trigger-payment-close',function(){
                            $(this).closest('.item-credit').find('div[data-role="trigger"]').trigger('click');
                        });

                        $(".half-part #half-part-collabsible").accordion({"collapsible": true});

                        $(document).on('change','.half-part .option-progres-bar',function(){
                            var current_month = $(this).val();
                            var resCalc_change = PP_CALCULATOR.calculatePhys(current_month, total_product_price);
                            $(".half-part .payment_count-info").html(parseInt(current_month) + 1);
                            $(".half-part .month_count-info").html(current_month);
                            // current_month = parseInt(current_month);
                            $('.half-part .payment_count-info + span').html(decOfNum(parseInt(current_month) + 1,[$t('payment'),$t('payments'),$t('of payments')]));
                            $(".half-part .month_count-info + span").html(decOfNum(current_month,[$t('month'),$t('months'),$t('of months')]));
                            // changed select part-half payment result price
                            $(".half-part .item-credit .price-box .price").text( resCalc_change['ppValue'] );
                            $('.half-part [name="payment[pp_price]"]').val(resCalc_change['ppValue']);
                            $('.half-part [name="payment[pp_term]"]').val(parseInt(current_month) + 1);
                        });
                        if(startValue){
                            $('.half-part .option-progres-bar').val(parseInt(startValue) + 1);
                            $('.half-part .option-progres-bar').trigger('change');
                        }
                        var code = {};
                        $(".half-part  select option").each(function () {
                            if(code[this.text]) {
                                $(this).remove();
                            } else {
                                code[this.text] = this.value;
                            }
                        });


                    }
                }
                return 1;
            }),

            /**
             * Has part payment
             * @returns {*}
             */
            isVisible: function () {
                let cartData = customerData.get('cart')();
                return !cartData.part_payment_message;
            },

            getData: function () {
                return {
                    'method': this.item.method,
                    'additional_data': {
                        'pp_term': $('[name="payment[pp_term]"]').val(),
                        'pp_price': $('[name="payment[pp_price]"]').val()
                    }
                };
            },

            getFormattedPrice: function (price) {
                return priceUtils.formatPrice(price, quote.getPriceFormat());
            },
            getTotals: function() {
                return totals.totals();
            },
            getCode: function() {
                return 'parts_payment';
            },
            isActive: function() {
                return true;
            },
            getErrorMessage: function() {
                let cartData = customerData.get('cart')();
                return cartData.part_payment_message;
            },
            validate: function() {
                var $form = $('#' + this.getCode() + '-form');
                return $form.validation() && $form.validation('isValid');
            },
            afterPlaceOrder: function () {
                $.post(url.build('payment/checkout/pbpartspayment'), {
                    'random_string': this._generateRandomString(30),
                    'pp_term': $('[name="payment[pp_term]"]').val(),
                    'pp_price': $('[name="payment[pp_price]"]').val()
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
            _generateRandomString: function(length) {
                if (!length) {
                    length = 10;
                }
                var text = '';
                var possible = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
                for (var i = 0; i < length; ++i) {
                    text += possible.charAt(Math.floor(Math.random() * possible.length));
                }
                return text;
            }
        });
    }
);