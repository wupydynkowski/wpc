<?php
/**
 * @package AWS Price Calculator
 * @author Enrico Venezia
 * @copyright (C) Altos Web Solutions Italia
 * @license GNU/GPL v2 http://www.gnu.org/licenses/gpl-2.0.html
**/

require_once('admin/resources/lib/eos/Stack.php');
require_once('admin/resources/lib/eos/Parser.php');

/*WPC-PRO*/
require_once('admin/resources/lib/PHPExcel/Classes/PHPExcel.php');
/*/WPC-PRO*/

require_once('admin/awsframework/Helper/FrameworkHelper.php');
        
class AWSPriceCalculator {

	var $plugin_label           = "Woo Price Calculator";
	var $plugin_code            = "woo-price-calculator";
        var $plugin_dir             = "woo-price-calculator";
        //var $plugin_short_code      = "woo_price_calc";
        var $plugin_short_code      = "aws_price_calc";
        var $plugin_db_version      = null;

        var $view = array();
        
        var $wsf = null;
        var $db;
        
        var $fieldHelper;
        var $calculatorHelper;
        
        var $fieldModel;
        
	public function __construct($plugin_db_version){
            
            global $wpdb;

            $this->wpdb                 = $wpdb;
            $this->plugin_db_version    = $plugin_db_version;
            
            add_action( 'save_post', array($this, 'save_post'));
            
            add_action('wp_enqueue_scripts', array($this, 'wp_enqueue_scripts'));
            add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
            
            add_action('admin_menu', array( $this, 'register_submenu_page'),99);

            add_action('woocommerce_before_add_to_cart_button', array($this, 'product_meta_end'));

            add_filter('woocommerce_cart_item_price', array($this, 'cartItemPrice'), 1, 3);
            add_filter('woocommerce_cart_item_price_html', array($this, 'woocommerce_cart_item_price_html'), 1, 3);
            add_filter('woocommerce_cart_product_subtotal', array($this, 'woocommerce_cart_product_subtotal'), 10, 4 ); 
            
            add_action('woocommerce_before_calculate_totals', array($this, 'woocommerce_before_calculate_totals'), 10, 1);
            add_action('woocommerce_add_to_cart', array($this, 'add_to_cart_callback'), 10, 6);

            add_action('woocommerce_cart_item_removed', array($this, 'action_woocommerce_cart_item_removed'), 10, 2 );

            add_action('woocommerce_add_order_item_meta', array($this, 'action_woocommerce_add_order_item_meta'), 1, 3 );
            add_action('woocommerce_checkout_update_order_meta', array($this, 'action_woocommerce_checkout_update_order_meta'), 10, 2);
            add_action('woocommerce_checkout_order_processed', array($this, 'action_woocommerce_checkout_order_processed'), 10, 1 );
                    
            add_action( 'add_meta_boxes', array($this, 'order_add_meta_boxes'));
            
            add_action('wp_ajax_awspricecalculator_ajax_callback', array($this, 'ajax_callback'));
            add_action('wp_ajax_nopriv_awspricecalculator_ajax_callback', array($this, 'ajax_callback'));

            add_filter('woocommerce_add_to_cart_validation', array($this, 'filter_woocommerce_add_to_cart_validation'), 10, 3);
            add_filter('woocommerce_add_to_cart_redirect', array($this, 'filter_woocommerce_add_to_cart_redirect'));
            add_filter('woocommerce_get_price_html', array($this, 'filter_woocommerce_get_price_html'), 10, 2);
            add_filter('woocommerce_cart_item_name', array($this, 'filter_woocommerce_cart_item_name'), 20, 3);
            /*WPC-PRO*/
            add_filter('woocommerce_checkout_cart_item_quantity', array($this, 'woocommerce_checkout_cart_item_quantity'), 10, 2);
            add_filter('woocommerce_order_item_quantity_html', array($this, 'woocommerce_order_item_quantity_html'), 10, 2);
            /*/WPC-PRO*/
            
            add_filter( 'woocommerce_loop_add_to_cart_link', array($this, 'woocommerce_loop_add_to_cart_link'), 10, 2 );
            
            add_action('plugins_loaded', array($this, 'action_plugins_loaded'));
                        
           // add_filter('admin_footer_text', array($this, 'filter_admin_footer_text'));
            
            $this->wsf               = new WSF\Helper\FrameworkHelper($this->plugin_dir, "wordpress");
            $this->wsf->setVersion($plugin_db_version);
            
            $this->databaseHelper    = $this->wsf->get('\\WSF\\Helper', true, 'awsframework/Helper', 'DatabaseHelper', array($this->wsf));
            
            $this->calculatorHelper = $this->wsf->get('\\WSF\\Helper', true, 'awspricecalculator/Helper', 'CalculatorHelper', array($this->wsf));
            $this->fieldHelper      = $this->wsf->get('\\WSF\\Helper', true, 'awspricecalculator/Helper', 'FieldHelper', array($this->wsf));
            $this->themeHelper      = $this->wsf->get('\\WSF\\Helper', true, 'awspricecalculator/Helper', 'ThemeHelper', array($this->wsf));
            $this->cartHelper       = $this->wsf->get('\\WSF\\Helper', true, 'awspricecalculator/Helper', 'CartHelper', array($this->wsf));
            $this->orderHelper      = $this->wsf->get('\\WSF\\Helper', true, 'awspricecalculator/Helper', 'OrderHelper', array($this->wsf));
            $this->pluginHelper     = $this->wsf->get('\\WSF\\Helper', true, 'awspricecalculator/Helper', 'PluginHelper', array($this->wsf));
            $this->productHelper    = $this->wsf->get('\\WSF\\Helper', true, 'awspricecalculator/Helper', 'ProductHelper', array($this->wsf));
            $this->ecommerceHelper  = $this->wsf->get('\\WSF\\Helper', true, 'awsframework/Helper', 'EcommerceHelper', array($this->wsf));
            
            $this->fieldModel       = $this->wsf->get('\\WSF\\Model', true, 'awspricecalculator/Model', 'FieldModel', array($this->wsf));
            $this->calculatorModel  = $this->wsf->get('\\WSF\\Model', true, 'awspricecalculator/Model', 'CalculatorModel', array($this->wsf));
            $this->settingsModel    = $this->wsf->get('\\WSF\\Model', true, 'awspricecalculator/Model', 'SettingsModel', array($this->wsf));
            
            /* Meglio lasciarlo sempre per ultimo affinchè siano istanziati gli oggetti */
            $this->pluginHelper->pluginUpgrade($this->plugin_db_version);
            
            /* Può eseguire azioni prima di qualsiasi altra stampa HTML impostando raw=1 */
            if($this->wsf->requestValue("page") == "woo-price-calculator" && $this->wsf->requestValue("raw") == true){
                $this->wsf->execute("awspricecalculator", true);
            }
	}
                
        /*
         * Eseguita al salvataggio di un post
         */
        function save_post($postId) {
            $post       = get_post($postId);

            if($post->post_type == "product"){
                /* Controllo duplicato Calcolatori, visualizzare errore */
            }
    
        }
               
        /*
         * Cambia la visualizzazione del pulsante Add to cart presente nell'archivio
         */
        function woocommerce_loop_add_to_cart_link($link, $product){
            $calculator  = $this->calculatorHelper->get_simulator_for_product($product->get_id());
            
            if(!empty($calculator)){
                $link = sprintf( '<a href="%s" rel="nofollow" data-product_id="%s" data-product_sku="%s" data-quantity="%s" class="button product_type_%s">%s</a>',
                    esc_url( get_permalink($product->get_id())),
                    esc_attr( $product->get_id()),
                    esc_attr( $product->get_sku()),
                    esc_attr( isset( $quantity ) ? $quantity : 1),
                    esc_attr($product->get_type()),
                    esc_html(__( 'Choose an option', 'woocommerce'))
                );
            }
            
            return $link;
        }

        function admin_enqueue_scripts($hookSuffix){
            $this->pluginHelper->adminEnqueueScripts($this->plugin_code, $hookSuffix);
        }
        
        function wp_enqueue_scripts(){
            $this->pluginHelper->frontEnqueueScripts($this->plugin_code, get_the_ID());
        }
        
        /*
         * Modifica il prezzo nella pagina del prodotto e nelle pagine dello shop
         * 
         * Visualizzo il prezzo all'inizio, prendo i valori di default per calcolare il prezzo di partenza
         */
        function filter_woocommerce_get_price_html($price, $product){

            if($product->post_type == "product"){
                $productId	= $product->get_id();
            }else if($product->post_type == "product_variation"){
                $productId	= $product->get_parent_id();
            }else{
                $productId	= null;
            }

            if(!empty($productId)){
                $simulator  = $this->calculatorHelper->get_simulator_for_product($productId);

                if(!empty($simulator)){
                    /* 
                     * Evito di visualizzare il prezzo nel backend, se ci sono tanti prodotti
                     * il programma ci impiega molto tempo a visualizzare la pagina
                     * Ma in ogni caso faccio visualizzare il plugin in caso di richiesta POST
                     * perchè ci potrebbero essere dei plugin che richiedono il prezzo come ad esempio
                     * YITH WooCommerce Quick View
                     */
                    if(!is_admin() || $this->wsf->isPost() == true){
                        try{
                            
                            $fieldValues        = $this->calculatorHelper->getFieldsDefaultValue($simulator->id);
                            
                            /* If added to cart, to get the right price, I will get the post data values */
                            if($this->wsf->isPost()){
                                $post   = $this->wsf->getPost();
                                
                                if(is_array($post)){
                                    if(!empty($post['add-to-cart'])){
                                        $fieldValues    = $post;
                                    }
                                }
                            }

                            $price		= $this->calculatorHelper->calculate_price($productId, $fieldValues, true, $simulator->id);
                        }catch (\Exception $ex) {
                            $price              = "Error: {$ex->getMessage()}";
                        }
                        $priceNetto = strip_tags($price);

                        return "<span class=\"woocommerce-Price-amount amount\">{$price}</span>";
                    }else{
                        return "Calculator Price";
                    }
                }
            }
		
            return $price;
        }
        
        /*
         * Modifica il nome del prodotto nella pagina del carrello
         */
        function filter_woocommerce_cart_item_name($productTitle, $cartItem, $cartItemKey){

            /*WPC-PRO*/
            if(is_cart() && $this->wsf->getLicense() == true){
                if(isset($cartItem['simulator_id'])){
                    $simulatorId                = $cartItem['simulator_id'];

                    if(!empty($simulatorId)){
                        $simulatorFieldsIds     = $this->calculatorHelper->get_simulator_fields($simulatorId);
                        $simulatorFields        = $this->fieldHelper->get_fields_by_ids($simulatorFieldsIds);
                        $simulatorFieldsData    = $cartItem['simulator_fields_data'];
                        $productId              = $cartItem['product_id'];

                        $this->calculatorHelper->calculate_price($productId, $simulatorFieldsData, false, $simulatorId, $outputResults, $conditionalLogic);
                                
                        $title = array(); 
                        foreach($simulatorFields as $simulatorKey => $simulatorField){
                            if($conditionalLogic[$simulatorField->id] == true){
                                $fieldId                    = $this->plugin_short_code . '_' . $simulatorField->id;
                                $value                      = (isset($simulatorFieldsData[$fieldId]))?$simulatorFieldsData[$fieldId]:null;
                                $fieldLabel                 = $this->wsf->userTrans($this->fieldHelper->getShortLabel($simulatorField));

                                $htmlElement                = $this->orderHelper->getReviewElement($simulatorField, $value);
                                $title[]                    = "<span class=\"no-wrap-desktop\">&emsp;&emsp;<b>{$fieldLabel}:</b> {$htmlElement}</span>";
                            }
                        }

                        return "{$productTitle}<br/><small>" . implode("<br/>", $title) . "</small><br/>";
                    }
                }
            }
            /*/WPC-PRO*/
            
            return $productTitle;
        }
        
        /*
         * Eseguito in review-order.php per rivedere l'ordine
         */
        /*WPC-PRO*/
        function woocommerce_checkout_cart_item_quantity($productTitle, $cartItem){
            if(isset($cartItem['simulator_id'])){
                $simulatorId            = $cartItem['simulator_id'];

                if(!empty($simulatorId)){
                    $simulatorFieldsIds     = $this->calculatorHelper->get_simulator_fields($simulatorId);
                    $simulatorFields        = $this->fieldHelper->get_fields_by_ids($simulatorFieldsIds);
                    $simulatorFieldsData    = $cartItem['simulator_fields_data'];
                    $productId              = $cartItem['product_id'];

                    $this->calculatorHelper->calculate_price($productId, $simulatorFieldsData, false, $simulatorId, $outputResults, $conditionalLogic);
                    
                    foreach($simulatorFields as $simulatorKey => $simulatorField){
                        if($conditionalLogic[$simulatorField->id] == true){
                            $fieldId                    = $this->plugin_short_code . '_' . $simulatorField->id;

                            $label                      = $this->wsf->userTrans($simulatorField->label);
                            $value                      = (isset($simulatorFieldsData[$fieldId]))?$simulatorFieldsData[$fieldId]:null;

                            $htmlElement                = $this->orderHelper->getReviewElement($simulatorField, $value);
                            $title[]                    = "&emsp;&emsp;<b>{$label}:</b> {$htmlElement}";
                        }
                    }

                    return "{$productTitle}<br/><small>" . implode("<br/>", $title) . "</small><br/>";
                }else{
                    return $productTitle; // Nessuna modifica se non è un simulatore
                }
            }
        }
        /*/WPC-PRO*/
        
        /*
         * Eseguito dopo l'acquisto, nel dettaglio dell'ordine
         */
        /*WPC-PRO*/
        function woocommerce_order_item_quantity_html($productTitle, $orderItem){
                        
            /*$orderId    = get_query_var('order-received');
            if(!empty($orderId)){
                $simulation                 = $this->calculatorModel->getSimulationByOrderId($orderId);

                if(!empty($simulation)){
                    $simulationData         = json_decode($simulation->simulation_data, true);
                   
                    if(isset($orderItem['item_meta']['_wpc_cart_item_key'][0])){
                        $cartItemKey            = $orderItem['item_meta']['_wpc_cart_item_key'][0];

                        $simulatorId            = $simulationData[$cartItemKey]['simulator_id'];
                        $simulatorFieldsIds     = $this->calculatorHelper->get_simulator_fields($simulatorId);
                        $simulatorFields        = $this->fieldHelper->get_fields_by_ids($simulatorFieldsIds);
                        $simulatorFieldsData    = $simulationData[$cartItemKey]['simulator_fields_data'];

                        foreach($simulatorFields as $simulatorKey => $simulatorField){
                            $fieldId                    = $this->plugin_short_code . '_' . $simulatorField->id;
                            $value                      = $simulatorFieldsData[$fieldId];

                            $htmlElement                = $this->orderHelper->getReviewElement($simulatorField, $value);
                            $title[] = "&emsp;&emsp;<b>{$simulatorField->label}:</b> {$htmlElement}";
                        }

                        return "{$productTitle}<br/><small>" . implode("<br/>", $title) . "</small><br/>";
                    }
                }

            }*/

            return $productTitle;
        }
        /*/WPC-PRO*/
        

        
        function filter_admin_footer_text () {
            echo "";
        } 

        /*
         * Dopo che è stato aggiunto un prodotto reindirizza direttamente
         * al checkout
         */
        function filter_woocommerce_add_to_cart_redirect() {

            global $woocommerce;
            
            $product_id = $this->wsf->requestValue('add-to-cart');
            if(!empty($product_id)){
                $simulator = $this->calculatorHelper->get_simulator_for_product($product_id);

                if(!empty($simulator)){
                    if($simulator->redirect == 1){
                        return $woocommerce->cart->get_checkout_url();
                    }
                }
            }

        } 

        /*
         * Attivazione dell'internazionalizzazione
         */
        function action_plugins_loaded() {
            load_plugin_textdomain($this->plugin_code, false, dirname( plugin_basename(__FILE__) ) . '/lang' );
        }

        /*
         * Validazione dei campi del simulatore all'aggiunta del prodotto
         * nel carrello
         */
        function filter_woocommerce_add_to_cart_validation( $bool, $product_id, $quantity){
            $simulator = $this->calculatorHelper->get_simulator_for_product($product_id);

            if(!empty($simulator)){
                $requestFields      = $this->calculatorHelper->getFieldsFromRequest($simulator->id);
                $errors             = $this->calculatorHelper->checkErrors($requestFields['fields'], $requestFields['data'], true, $product_id);
                
                if(count($errors) != 0){
                    foreach($errors as $fieldId => $fieldErrors){
                        foreach($fieldErrors as $errorMessage){
                            wc_add_notice($errorMessage, "error");
                        } 
                    }
                    
                    return false;
                }

                /*
                 * Svuota il carello prima di ogni aggiunta di prodotto (Se l'opzione è attiva)
                 */
                if($simulator->empty_cart == 1){
                    $this->ecommerceHelper->emptyCart();
                }
                
                /* Calcolo i valori di output */
                $this->calculatorHelper->calculate_price($product_id, $requestFields['data'], false, $simulator->id, $outputFieldsData);
                        
                /*
                 * Aggiungo il prodotto. Il prodotto inserito di default da WC
                 * sarà cancellato nella funzione add_to_cart_callback
                 */
                WC()->cart->add_to_cart($product_id, $quantity, 0, array(), array(
                    'simulator_id'              => $simulator->id,
                    'simulator_fields_data'     => $requestFields['data'],
                    'output_fields_data'        => $outputFieldsData,
                ));
            }
            
            return true;
        }
        

        /*
         * Aggiungo ulteriori informazioni nell'ordine che mi saranno utili in futuro.
         * 
         * Potrei anche utilizzare direttamente questo metodo per salvare i dati
         * della tabella "woopricesim_simulations" nell'ordine.
         */
        function action_woocommerce_add_order_item_meta($item_id, $values, $cart_item_key){
            $simulatorId    = $values['simulator_id'];
        	
            if(isset($simulatorId)) {
            	/*WPC-PRO*/
		$simulatorFieldsIds     = $this->calculatorHelper->get_simulator_fields($values['simulator_id']);
            	$simulatorFields        = $this->fieldHelper->get_fields_by_ids($simulatorFieldsIds);
                
                $simulatorFieldsData	= $values['simulator_fields_data'];
                $outputFieldsData       = $values['output_fields_data'];
                $productId              = $values['product_id'];
                
                $this->calculatorHelper->calculate_price($productId, $simulatorFieldsData, false, $simulatorId, $outputResults, $conditionalLogic);
                
            	foreach($simulatorFields as $simulatorKey => $simulatorField){
                    if($conditionalLogic[$simulatorField->id] == true){
                        $fieldId                    = $this->plugin_short_code . '_' . $simulatorField->id;
                        $value                      = $simulatorFieldsData[$fieldId];
                        $htmlElement                = $this->orderHelper->getReviewElement($simulatorField, $value, true);
                        $label                      = strip_tags($this->wsf->userTrans($simulatorField->label));

                        wc_add_order_item_meta($item_id, $label,  $htmlElement);
                    }
            			
            	}
                
                foreach($outputFieldsData as $fieldId => $fieldValue){
                    $field          = $this->fieldModel->get_field_by_id($fieldId);
                    $label          = strip_tags($this->wsf->userTrans($field->label));
                    $htmlElement    = $this->orderHelper->getReviewElement($field, $fieldValue);
                    
                    wc_add_order_item_meta($item_id, $label, $htmlElement);
                }
                
            	/*/WPC-PRO*/
            	wc_add_order_item_meta($item_id, "_wpc_cart_item_key", $cart_item_key);
            }
        }
        
        
        /*
         * Eseguito prima di effettuare il checkout
         * 
         * E' possibile prendere le informazioni inserite dall'utente in fase 
         * di checkout
         */
        function action_woocommerce_checkout_update_order_meta($order_id, $values){
        	/*WPC-PRO*/
		if(!empty($order_id)){
        		$simulation                 = $this->calculatorModel->getSimulationByOrderId($orderId);
        	
        		if(!empty($simulation)){
        			$simulationData         = json_decode($simulation->simulation_data, true);
        			 
        			if(isset($orderItem['item_meta']['_wpc_cart_item_key'][0])){
        				$cartItemKey            = $orderItem['item_meta']['_wpc_cart_item_key'][0];
        	
        				$simulatorId            = $simulationData[$cartItemKey]['simulator_id'];
        				$simulatorFieldsIds     = $this->calculatorHelper->get_simulator_fields($simulatorId);
        				$simulatorFields        = $this->fieldHelper->get_fields_by_ids($simulatorFieldsIds);
        				$simulatorFieldsData    = $simulationData[$cartItemKey]['simulator_fields_data'];
        	
        				foreach($simulatorFields as $simulatorKey => $simulatorField){
        					$fieldId                    = $this->plugin_short_code . '_' . $simulatorField->id;
        					$value                      = $simulatorFieldsData[$fieldId];
                                                $htmlElement                = $this->orderHelper->getReviewElement($simulatorField, $value);
        	
        					update_post_meta($order_id, $this->wsf->userTrans($simulatorField->label), $htmlElement );
        				}
        	
        			}
        	   }
        	
        	}
        	
        		
        	/*/WPC-PRO*/
        }
        
        /*
         * Salvataggio della simulazione nel database, quando l'utente proccede
         * all'ordine
         */
        function action_woocommerce_checkout_order_processed($order_id){
            $orderData                  = array();
            $simulatorsDataBackup       = array();
            $foundSimulators            = false;
            
            foreach (WC()->cart->get_cart() as $cart_item_key => $values){
                if(isset($values['simulator_id'])){
                    $foundSimulators                = true;
                    $simulatorId                    = $values['simulator_id'];
                    
                    $orderData[$cart_item_key]      = $values;
                    
                    if(!array_key_exists($simulatorId, $simulatorsDataBackup)){
                        $simulatorsDataBackup[$simulatorId]     = $this->calculatorModel->get($simulatorId);
                    }
                }
            }

            if($foundSimulators === true){
                $this->calculatorModel->saveSimulation($order_id, $orderData, $simulatorsDataBackup);
            }
        }
    
        /*
         * Aggiunta di un blocco negli Ordini
         */
        public function order_add_meta_boxes(){
            add_meta_box( 
                'woocommerce-order-my-custom', 
                "Price Calculator", 
                array($this,'order_simulation'), 
                'shop_order', 
                'normal', 
                'default' 
            );
        }
        
        
        /*
         * Visualizzazione di tutte le simulazioni per quell'ordine
         */
        public function order_simulation($order){
            echo $this->orderHelper->calculatorOrder($order->ID);
        }
        
        /*
         * Eseguito alla rimozione di un prodotto dal carrello
         */
        function action_woocommerce_cart_item_removed($cart_item_key, $instance){

        }

        /*
         * Eseguito per gli elementi nel carrello
         */
        function cartItemPrice($product_name, $values, $cart_item_key){
            global $woocommerce;

            $product    = $this->ecommerceHelper->getProductById($values['product_id']);
            $cartItem   = $woocommerce->cart->get_cart_item($cart_item_key);
            
            if(isset($cartItem['simulator_id'])){
                $calculatorId           = $cartItem['simulator_id'];
                $fieldsData             = $cartItem['simulator_fields_data'];
                
                $price                  = $this->calculatorHelper->calculate_price($values['product_id'], $fieldsData, true, $calculatorId, $outputResults, $conditionalLogic);
                $calculator             = $this->calculatorModel->get($calculatorId);
                $simulatorFieldsIds     = $this->calculatorHelper->get_simulator_fields($calculator->id);
                $simulatorFields        = $this->fieldHelper->get_fields_by_ids($simulatorFieldsIds);

                /*
                   * controllo se ci sta un problema su i campi dell calcolatore,
                   * in caso di si, rimuovo il prodotto dal carrello
                   */
                foreach($simulatorFields as $simulatorKey => $simulatorField) {

                    if ($simulatorField == null) {
                        WC()->cart->remove_cart_item($cart_item_key);
                        return null;
                    }
                }


                /* Non faccio vedere il tasto modifica nel carrello dropdown */
                if(is_cart() == true){
                    $calculatorFieldsIds    = $this->calculatorHelper->get_simulator_fields($calculator->id);
                    $calculatorFields       = $this->fieldHelper->get_fields_by_ids($calculatorFieldsIds);
                    $defaultThemeData       = $this->themeHelper->getDefaultThemeData($simulatorFields, $fieldsData);
                    
                    return $this->wsf->getView('awspricecalculator', 'cart/edit.php', true, array(
                        'product'               => $product,
                        'cartItemKey'           => $cart_item_key,
                        'price'                 => $price,
                        'cartEditButtonClass'   => $this->settingsModel->getValue("cart_edit_button_class"),
                        'modal'             =>  $this->wsf->getView('awspricecalculator', 'product/product.php', true, array(
                            'product'               => $product,
                            'simulator'             => $calculator,
                            'data'                  => $defaultThemeData,
                            'outputResults'         => $this->calculatorHelper->getOutputResultsPart($calculator, $outputResults),
                            'conditionalLogic'      => $conditionalLogic,
                        )) . $this->wsf->getView("awspricecalculator", "product/footer_data.php", true, array(
                            'product'               => $product,
                            'simulator'             => $calculator,
                            'data'                  => $defaultThemeData,
                            'imagelist_modals'      => $this->wsf->getView("awspricecalculator", 'partial/imagelist_modal.php', true, array(
                                'simulator_fields'  => $calculatorFields,
                                'fieldHelper'       => $this->fieldHelper,
                                'cartItemKey'       => $cart_item_key,
                                'data'              => $defaultThemeData,
                            )),
                        )),
                    ));
                }else{
                    return $price;
                }

            }
            
            return $product_name;
        }

        /*
         * Eseguito per gli elementi nel carrello (Versione HTML)
         * 
         * Questo prezzo viene anche visualizzato nel carrello dropdown
         */
        function woocommerce_cart_item_price_html($cart_price, $values, $cart_item_key){
            global $woocommerce;
            $product = new WC_Product($values['product_id']);

            $cartItem   = $woocommerce->cart->get_cart_item($cart_item_key);
            
            if(isset($cartItem['simulator_id'])){
                $calculatorId   		= $cartItem['simulator_id'];
                $fieldsData     		= $cartItem['simulator_fields_data'];
                $price                  = $this->calculatorHelper->calculate_price($values['product_id'], $fieldsData);
                $calculator             = $this->calculatorModel->get($calculatorId);
                $simulatorFieldsIds     = $this->calculatorHelper->get_simulator_fields($calculator->id);
                $simulatorFields        = $this->fieldHelper->get_fields_by_ids($simulatorFieldsIds);
                    
                return $price;

            }
            
            return $cart_price;
        }
        
        /*
         * Eseguito nella visualizzazione del sotto totale del prodotto nel carrello
         */
        function woocommerce_cart_product_subtotal($product_subtotal, $product, $quantity, $cart_object){
            
            $this->cartHelper->updateCartByCartObject($cart_object);
            
            return $product_subtotal; 
        }
        
        /*
         * Eseguito all'aggiunta di un prodotto nel carrello
         * 
         * |Imposto la quantità sul carrello|
         * $woocommerce->cart->set_quantity($cart_item_key, 100, true);
         * 
         * |Ricalcola i totali del carrello|
         * $woocommerce->cart->calculate_totals();
         */
	public function add_to_cart_callback($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data){
                global $woocommerce;

                $simulator = $this->calculatorHelper->get_simulator_for_product($product_id);
                
                if(!empty($simulator)){
                    
                    /*
                     * Rimuovo il prodotto inserito da WC. Con questo trucco,
                     * riesco ad inserire prodotti con dati diversi nel carrello,
                     * riuscendo a separarli per ogni riga.
                     */
                    foreach(WC()->cart->get_cart() as $cart_item_key => $values ){
                        $cartProductId  = $values['product_id'];
                        $cartSimulator  = $this->calculatorHelper->get_simulator_for_product($cartProductId);
                        
                        /* Controllo che il prodotto nel carrello sia associato ad un simulatore */
                        if(empty($values['simulator_id']) && !empty($cartSimulator)){
                            $woocommerce->cart->remove_cart_item($cart_item_key);
                        }
                    }
                    
                    $simulator_fields_ids = $this->calculatorHelper->get_simulator_fields($simulator->id);
                    $fields = $this->fieldHelper->get_fields_by_ids($simulator_fields_ids);

                    /*
                     * controllo se ci sta un problema su i campi dell calcolatore,
                     * in caso di si, rimuovo il prodotto dal carrello
                     */
                    foreach($fields as $simulatorKey => $simulatorField) {

                        if ($simulatorField == null) {
                            WC()->cart->remove_cart_item($cart_item_key);

                        }
                    }


                }
	}
	
        /*
         * Eseguito prima di effettuare il calcolo del totale in cart/checkout
         * Permette di calcolare e cambiare il peso per ogni prodotto
         */
	public function woocommerce_before_calculate_totals($cart_object){
            
            if (sizeof($cart_object->cart_contents ) > 0) {

                foreach ($cart_object->cart_contents as $cartItemKey => $cartItem) {

                    $productId      = $cartItem['product_id'];
                    $product        = new WC_Product($productId);
                    $calculator     = $this->calculatorHelper->get_simulator_for_product($productId);

                     /* E' un calcolatore */
                    if(!empty($calculator)){
                        /* E' stato impostato il campo di overwrite per il peso */
                        if(!empty($calculator->overwrite_weight)){ 
                            $calculatorFieldsData    = $cartItem['simulator_fields_data'];
                            $this->calculatorHelper->calculate_price($productId, $calculatorFieldsData, false, $calculator->id, $outputResults);
                            $cartItem['data']->set_weight($outputResults[$calculator->overwrite_weight]);
                        }
                    }
                }
            }
        
            $this->cartHelper->updateCartByCartObject($cart_object);
	}

        /*
         * Funzione richiamata via Ajax per il calcolo in real-time del prezzo
         */
	public function ajax_callback(){
            $this->pluginHelper->ajaxCallback();
	}
        
        /*
         * Visualizzazione del simulatore nella scheda prodotto
         */
	public function product_meta_end(){
            echo $this->productHelper->productPage(get_the_ID());
	}

        /*
         * Aggiunge una voce al menù di WooCommerce
         */
	public function register_submenu_page() {
    		add_submenu_page('woocommerce', 
                        $this->plugin_label, 
                        $this->plugin_label, 
                        'manage_woocommerce', 
                        $this->plugin_code, 
                        array($this, 'submenu_callback')
                        ); 
	}

        /*
         * Visualizza il backend del plugin
         */
	public function submenu_callback() {
                echo $this->wsf->execute('awspricecalculator', true);
	}

        

}