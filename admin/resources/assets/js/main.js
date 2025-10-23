/**
 * @package AWS Price Calculator
 * @author Enrico Venezia
 * @copyright (C) Altos Web Solutions Italia
 * @license GNU/GPL v2 http://www.gnu.org/licenses/gpl-2.0.html
**/

jQuery(document).ready(function($){
    var requestAjaxCalculatePrice;
    var defaultProductImage;
    
    WooPriceCalculator = {
        init: function(){
            
            /*
            defaultProductImage = WooPriceCalculator.getDefaultProductImage();
            */
           
            $('.woo-price-calculator-tooltip').tooltipster({
                animation: 'fade',
                contentAsHTML: true,
                multiple: true,
                theme: 'tooltipster-shadow',
                touchDevices: true,
                'maxWidth': 300
            });
    
            if($('.wpc-cart-form').length){
                $('.wpc-cart-form').each(function(index, element){
                    $('.wpc-cart-edit', element).click(function(){
                        var productId           = $('.wpc_product_id', element).val();
                        var simulatorId         = $('.wpc_simulator_id', element).val();
                        var cartItemKey         = $(element).attr('data-cart-item-key');
                        var remodalInst         = $('[data-remodal-id="wpc_cart_item_' + cartItemKey + '"]').remodal();
                        var editButtons         = $('[data-remodal-target="wpc_cart_item_' + cartItemKey + '"]');
                        
                        //Evito che i prodotti si aggiungano automaticamente ad ogni calcolo (add-to-cart)
                        var data                = $(element).find(WooPriceCalculator.getFieldSelector(), element).serialize();
                        
                        var quantity            = 0;
                        if(WooPriceCalculator.getTargetEcommerce() == "woocommerce"){
                            quantity            = parseInt($("input[name='cart[" + cartItemKey + "][qty]']").val());
                        }else if(WooPriceCalculator.getTargetEcommerce() == "hikashop"){
                            quantity            = parseInt($("input[name='item[" + cartItemKey + "]']").val());
                        }
                    
                        $('.cart_item .product-price').html(WooPriceCalculator.htmlLoadingImage("awspricecalculator_loading"));
                        
                        WooPriceCalculator.ajaxEditCartItem(cartItemKey, productId, simulatorId, quantity, data);
                        remodalInst.close();
                    });
                });
            }
            
            /*
             * Inizializzazione dei componenti per data & ora
             */
            $('.awspc-field-widget').each(function(index, element){
                var fieldId             = $(element).attr('id');
                var fieldContainer      = $(".awspc-field", element);

                var options             = JSON.parse($('#' + fieldId + "_options").val());
                
                $(".aws_price_calc_date input", element).xdsoft_datetimepicker({
                    timepicker: false,
                    format: 'Y-m-d',
                    lazyInit: true,
                    validateOnBlur: false,
                    allowBlank: true,
                    scrollInput: false,
                    closeOnDateSelect: true,
                });
            
                $(".aws_price_calc_time input", element).xdsoft_datetimepicker({
                    datepicker: false,
                    format: 'H:i:s',
                    lazyInit: true,
                    validateOnBlur: false,
                    allowBlank: true,
                    scrollInput: false,
                });
                
                $(".aws_price_calc_datetime input", element).xdsoft_datetimepicker({
                    format: 'Y-m-d H:i:s',
                    lazyInit: true,
                    validateOnBlur: false,
                    allowBlank: true,
                    scrollInput: false,
                });
                
                if(fieldContainer.hasClass('aws_price_calc_numeric')){
                    var field               = $('input', fieldContainer);
                    var decimals            = options['numeric']['decimals'];
                    var decimalSeparator    = options['numeric']['decimal_separator'];

                    if(decimals){
                        decimals            = parseInt(decimals);
                    }else{
                        decimals            = 2;
                    }
                    
                    /* If Number of Decimals = 0, this means no decimals */
                    if(decimals == 0){
                        decimalSeparator    = false;
                    }

                    $(field).numeric({ 
                        decimalPlaces:  decimals,
                        decimal:        decimalSeparator,
                    });
                }
                
            });

            WooPriceCalculator.initFieldEvents();
            
            /*
             * Controllo qualsiasi richiesta ajax eseguita nel carrello.
             * In questo modo so se è stato aggiornato
             */
            setTimeout(function() { 
                $('.remodal').remodal(); 
            }, 500);
            
            if(WPC_HANDLE_SCRIPT.is_cart == true){
                $(document).ajaxComplete(function(event, xhr, settings) {
                    if($('.woocommerce .cart_item').length){
                        //Rinizializzo i modal
                        $('.remodal').remodal();  
                    }
                });
            }
            
        },
        
        hideOutputFields: function(){
            $('.awspc-output-product').hide();
        },
        
        showOutputFields: function(){
            $('.awspc-output-product').show();
        },
        
        hidePrice: function(cartItemKey){
            var priceSelector       = WooPriceCalculator.getPriceSelector();
            
            if(cartItemKey != null){
                var cartModalContainer  = $('[data-cart-item-key="' + cartItemKey + '"]');
                
                $('.wpc-cart-item-price', cartModalContainer).hide();
                $('.wpc-cart-edit', cartModalContainer).prop('disabled', true);
            
            }else{
                $(priceSelector).hide();
                $('form[name="hikashop_product_form"] .hikashop_product_price_main').hide();
            }
            
            WooPriceCalculator.hideOutputFields();
        },
        
        showPrice: function(cartItemKey){
            var priceSelector       = WooPriceCalculator.getPriceSelector();
            
            if(cartItemKey != null){
                var cartModalContainer  = $('[data-cart-item-key="' + cartItemKey + '"]');
                
                $('.wpc-cart-item-price', cartModalContainer).show();
                $('.wpc-cart-edit', cartModalContainer).prop('disabled', false);
            }else{
                $(priceSelector).show();
                $('form[name="hikashop_product_form"] .hikashop_product_price_main').show();
            }
            
            WooPriceCalculator.showOutputFields();

        },
        
        setFieldError: function(element, error){
            $(element).html(error);
        },
                
        alertError: function(message){
            alert("AWS Price Calculator Error: " + message);
        },
        
        /* Get the selector for the product price */
        getPriceSelector: function(){
            
            /* Get Ajax Class from settings */
            var singleProductAjaxHookClass  = WPC_HANDLE_SCRIPT.single_product_ajax_hook_class;
            
            /* Checking if user has defined WooCommerce Price classes to hook */
            if(singleProductAjaxHookClass){
                if($(singleProductAjaxHookClass).length){
                    return singleProductAjaxHookClass;
                }
            }
            
            /* If not, I will try standard classes: */
            
            /*
             * Bisogna evitare che il prezzo sia aggiornato dove non si deve
             * all'interno della stessa pagina
             */
            if($(".product .summary .price .woocommerce-Price-amount").length){
		return '.product .summary .price .woocommerce-Price-amount';
            }
			            
            if($(".single-product .product_infos .price .woocommerce-Price-amount").length){
                return ".single-product .product_infos .price .woocommerce-Price-amount";
            }
            
            if($(".product .summary .price").length){
		return '.product .summary .price';
            }
            
            if($(".single-product .product_infos .price").length){
                return ".single-product .product_infos .price";
            }
            
            if($(".wpc-cart-form .price").length){
		return '.wpc-cart-form .price';
            }
			
            if($(".product .price-box .amount").length){
		return '.product .price-box .amount';
            }
			
            if($(".product-details .product-item_price .price").length){
		return '.product-details .product-item_price .price';
            }
			
            if($('form[name="hikashop_product_form"] .hikashop_product_price').length){
		return 'form[name="hikashop_product_form"] .hikashop_product_price';
            }
             
            if($('.product-main .product-page-price').length){
		return '.product-main .product-page-price';
            }

            WooPriceCalculator.alertError("Unable to select Ajax WooCommerce Price class, read: https://altoswebsolutions.com/documentation/9-the-price-doesn-t-change");
        },
        
        getFieldSelector: function(){
            return '.awspc-field input, ' + 
                   '.awspc-field select'
            ;
        },

        htmlLoadingImage: function(cssClass){
            return "<img class=\"" + cssClass + "\" src=\"" + WPC_HANDLE_SCRIPT.resources_url + "/assets/images/ajax-loader.gif\" />";
        },
        
        conditionalLogic: function(logic, cartItemKey){

            $.each(logic, function(fieldId, displayField){
                var fieldContainer  = $('.awspc-field-row[data-field-id="' + fieldId + '"]');

                if(displayField == 1){
                    $(fieldContainer).show();
                }else{
                    $(fieldContainer).hide();
                }
            });
        },
        
        getFieldContainer: function(fieldId, cartItemKey){
            if(cartItemKey != null){
                var cartModalContainer  = $('[data-cart-item-key="' + cartItemKey + '"]');
                var fieldContainer      = $("#" + fieldId, cartModalContainer);
            }else{
                var fieldContainer      = $("form.cart #" + fieldId + ', form[name="hikashop_product_form"] #' + fieldId);
            }
            
            return fieldContainer;
        },
                
        ajaxCalculatePrice: function(productId, simulatorId, cartItemKey, data, outputEl){

            WooPriceCalculator.showPrice(cartItemKey);
            WooPriceCalculator.hideOutputFields();
            /* WooPriceCalculator.showProductImageLoading(); */
            
            $(outputEl).html(WooPriceCalculator.htmlLoadingImage("awspricecalculator_loading"));
            
            $(".awspc-field-error").html("");
            
            if(requestAjaxCalculatePrice && requestAjaxCalculatePrice.readyState != 4){
                requestAjaxCalculatePrice.abort();
            }

            requestAjaxCalculatePrice = $.ajax({
                method: "POST",
                url: WPC_HANDLE_SCRIPT.ajax_url + "&id=" + productId + "&simulatorid=" + simulatorId,
                dataType: 'json',
                data: data,

                success: function(result, status, xhrRequest) {

                    /* WooPriceCalculator.productImageLogic(result.productImageLogic); */
                    WooPriceCalculator.conditionalLogic(result.conditionalLogic, cartItemKey);
                    
                    if(result.errorsCount == 0){
                        $(".awspc-output-product").html(WooPriceCalculator.decodeUtf8(result.outputFields));

                        // var temp = result.price.match("&quot;&gt;[(A-Z0-9.)]*&amp;")[0];
                        // var simplePrice = temp.replace("", "").replace("", "").replace("&quot;&gt;", "").replace("&amp;", "");
                        // var bruttoPrice = parseFloat(simplePrice) * 1.23;
                        var decodeHtml = WooPriceCalculator.decodeHtml(WooPriceCalculator.decodeUtf8(result.price));
                        //decodeHtml = decodeHtml.replace(simplePrice, simplePrice + " " + bruttoPrice);

                        $(outputEl).html(decodeHtml);
                        $(outputEl).show();
                        WooPriceCalculator.showOutputFields();
                    }else{
                        WooPriceCalculator.hidePrice(cartItemKey);

                        $.each(result.errors, function(fieldId, fieldErrors){
                            $.each(fieldErrors, function(index, fieldError){
                                
                                var error               = $(".awspc-field-error", WooPriceCalculator.getFieldContainer(fieldId, cartItemKey));
                                    
                                $(error).html(fieldError);
                                //console.log(fieldId + ": " + fieldError);
                            });
                        });
                    }
                    
                    $('.wpc-product-form').show();
                },
                error: function(xhrRequest, status, errorMessage)  {
                   //alert("Sorry, an error occurred");
                   console.log("AWS Price Calculator Error: " + errorMessage);
                }
           });
        },
        
        ajaxEditCartItem: function(cartItemKey, productId, simulatorId, quantity, data){
            $.ajax({
                method: "POST",
                url: WPC_HANDLE_SCRIPT.ajax_url + "&id=" + productId + 
                     "&simulatorid=" + simulatorId + 
                     "&wpc_action=edit_cart_item" +
                     "&cart_item_key=" + cartItemKey + 
                     "&quantity=" + quantity,
             
                data: data,

                success: function(result, status, xhrRequest){
                    location.reload();
                    
                    //console.log(result);
                },
                error: function(xhrRequest, status, errorMessage){
                   console.log("Error: " + errorMessage);
                }
           });
        },
                
        wooCommerceUpdateCart: function(){
            $('[name="update_cart"]').trigger('click');
        },
        
        calculatePrice: function(){
            /* API: awspcBeforeCalculatePrice */
            $(document).trigger("awspcBeforeCalculatePrice");
            
            /* Si potrebbe anche fare che sia l'utente ad impostare la classe di cambio del prezzo, nel caso sia utilizzati plugin che modificano la parte del prezzo */
            if(WPC_HANDLE_SCRIPT.is_cart == true){
                if($('.wpc-cart-form').length){
                    WooPriceCalculator.calculateCartPrice();
                }
            }else{
                if($('.wpc-product-form').length){
                    WooPriceCalculator.calculateProductPrice();
                }
            }
            
            /* API: awspcAfterCalculatePrice */
            $(document).trigger("awspcAfterCalculatePrice");
        },
                
        calculateCartPrice: function(){
            var element             = window.wpcCurrentCartItem;
            var productId           = $('.wpc_product_id', element).val();
            var simulatorId         = $('.wpc_simulator_id', element).val();
            //Evito che i prodotti si aggiungano automaticamente ad ogni calcolo (add-to-cart)
            var data                = $(element).find(WooPriceCalculator.getFieldSelector(), element).serialize();
            var cartItemKey         = $(element).attr('data-cart-item-key');

            //console.log(data);

            WooPriceCalculator.ajaxCalculatePrice(productId, simulatorId, cartItemKey, data, $('.price', element).first());

        },
        
        calculateProductPrice: function(){
            var productId           = $('form.cart .wpc_product_id, [name="hikashop_product_form"] .wpc_product_id').val();
            var simulatorId         = $('form.cart .wpc_simulator_id, [name="hikashop_product_form"] .wpc_simulator_id').val();
            var priceSelector       = WooPriceCalculator.getPriceSelector();

            //Evito che i prodotti si aggiungano automaticamente ad ogni calcolo (add-to-cart)
            var data                = $('form.cart .wpc-product-form, [name="hikashop_product_form"] .wpc-product-form').find(WooPriceCalculator.getFieldSelector()).serialize();
            WooPriceCalculator.ajaxCalculatePrice(productId, simulatorId, null, data, $(priceSelector));
        },
        
        getTargetEcommerce: function(){
            return WPC_HANDLE_SCRIPT.target_ecommerce;
        },

        encodeUtf8: function(s){
            return encodeURIComponent(s);
        },
        
        decodeUtf8: function(s){
            return decodeURIComponent(s);
        },

        initFieldEvents: function(){
            var timeout             = false;
            var writingTimeout      = 250;
            
            if(WPC_HANDLE_SCRIPT.is_cart == true){
                $(document).on('opening', '.remodal', function (){
                    window.wpcCurrentCartItem    = $(this);
                    WooPriceCalculator.calculateCartPrice();
                    
                    //console.log('Cart Item has been opened: ' + $(this).attr('data-cart-item-key'));
                });
            }
            
            $(document).on('keyup', '.aws_price_calc_numeric input', function(){
                if(timeout){ 
                    clearTimeout(timeout); 
                }

                timeout = setTimeout(function () {
                      WooPriceCalculator.calculatePrice();
                }, writingTimeout);
            });
            
            /* Per gli elementi di tipo Range */
            $(document).on('change', '.aws_price_calc_numeric input[type=range]', function(){
                if(timeout){ 
                    clearTimeout(timeout); 
                }

                timeout = setTimeout(function () {
                      WooPriceCalculator.calculatePrice();
                }, writingTimeout);
            });
                        
            $(document).on('keyup', '.aws_price_calc_text input', function(){
                if(timeout){ 
                    clearTimeout(timeout); 
                }

                timeout = setTimeout(function () {
                      WooPriceCalculator.calculatePrice();
                }, writingTimeout);
            });

            $(document).on('change', '.aws_price_calc_date input', function(){
                WooPriceCalculator.calculatePrice();
            });
            
            $(document).on('change', '.aws_price_calc_time input', function(){
                WooPriceCalculator.calculatePrice();
            });
            
            $(document).on('change', '.aws_price_calc_datetime input', function(){
                WooPriceCalculator.calculatePrice();
            });
            
            $(document).on('change', '.aws_price_calc_picklist select', function(){
                WooPriceCalculator.calculatePrice();
            });

            $(document).on('change', '.aws_price_calc_radio input', function(){
                WooPriceCalculator.calculatePrice();
            });

            $(document).on('change', '.aws_price_calc_checkbox input', function(){
                WooPriceCalculator.calculatePrice();
            });
            
            /* Image List: Mouseover on a modal element */
            $(document).on('mouseover', '.awspc-modal-imagelist-row', function(){
                $('.awspc-modal-imagelist-row').removeClass('awspc-modal-imagelist-hover');
                $(this).addClass('awspc-modal-imagelist-hover');
            });
            
            /* Image List: Click on a modal element */
            $(document).on('click', '.awspc-modal-imagelist-row', function(){

                /* Getting al elements I need */
                var cartItemKey        = $(this).attr('data-cart-item-key');
                var imagelistId        = $(this).attr('data-imagelist-id');
                var label              = $(this).attr('data-label');
                var itemId             = $(this).attr('data-item-id');
                var modalSelector      = $('[data-remodal-id="awspc_modal_imagelist_' + imagelistId + '"]');
                var remodalInst        = $(modalSelector).remodal();
                var clickedImageSrc    = $(this).find('img').attr('src');
                var hiddenSelector     = $('#aws_price_calc_' + imagelistId +' input[type="hidden"]');
                var buttonSelector     = $('#aws_price_calc_' + imagelistId + ' button');
                var textSelector       = $(buttonSelector).find('.awspc_modal_imagelist_text');
                var imageSelector      = $(buttonSelector).find('img');

                /* Hidding old clicked images */
                $(modalSelector).find('.awspc-modal-imagelist-row').removeClass('awspc-modal-imagelist-clicked');
                
                /* Changing button image */
                $(textSelector).html(label);
                $(imageSelector).attr('src', clickedImageSrc);
                $(hiddenSelector).val(itemId);
                
                /* Changing style */
                $(this).removeClass('awspc-modal-imagelist-hover');
                $(this).addClass('awspc-modal-imagelist-clicked');
                
                /* Close Modal */
                remodalInst.close();
                
                /* If the client is in cart, I re-open the item modal popup */
                if(cartItemKey){
                    $('[data-remodal-id="wpc_cart_item_' + cartItemKey + '"]').remodal().open();
                }
                
                /* Recalculate price */
                WooPriceCalculator.calculatePrice();
            });
            

        },
        
        encodeHtml: function(value){
          return $('<div/>').text(value).html();
        },

        decodeHtml: function(value){
          return $('<div/>').html(value).text();
        },
                
    };
    
    WooPriceCalculator.init();

});