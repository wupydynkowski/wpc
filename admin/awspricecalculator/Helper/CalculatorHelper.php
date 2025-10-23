<?php
/**
 * @package AWS Price Calculator
 * @author Enrico Venezia
 * @copyright (C) Altos Web Solutions Italia
 * @license GNU/GPL v2 http://www.gnu.org/licenses/gpl-2.0.html
**/

namespace WSF\Helper;

/*AWS_PHP_HEADER*/

use WSF\Helper\FrameworkHelper;

class CalculatorHelper {
    
    var $wsf;
    
    var $fieldHelper;
    var $ecommerceHelper;
    
    public function __construct(FrameworkHelper $wsf) {
        $this->wsf = $wsf;
        
        /* HELPERS */
        $this->databaseHelper               = $this->wsf->get('\\WSF\\Helper', true, 'awsframework/Helper', 'DatabaseHelper', array($this->wsf));
        $this->fieldHelper                  = $this->wsf->get('\\WSF\\Helper', true, 'awspricecalculator/Helper', 'FieldHelper', array($this->wsf));
        $this->ecommerceHelper              = $this->wsf->get('\\WSF\\Helper', true, 'awsframework/Helper', 'EcommerceHelper', array($this->wsf));
        $this->themeHelper                  = $this->wsf->get('\\WSF\\Helper', true, 'awspricecalculator/Helper', 'ThemeHelper', array($this->wsf));
        
        /* MODELS */
        $this->fieldModel                   = $this->wsf->get('\\WSF\\Model', true, 'awspricecalculator/Model', 'FieldModel', array($this->wsf));
        $this->calculatorModel              = $this->wsf->get('\\WSF\\Model', true, 'awspricecalculator/Model', 'CalculatorModel', array($this->wsf));
    }

    /*
     * 
     * Calcolo del prezzo utilizzando le formule inserite nel simulatore
     * 
     * $productId: L'ID del prodotto su cui calcolare il prezzo
     * $data: Sono i valori dei campi
     * $format_price: Come deve essere formatato il prezzo
     * $calculatorId: Se è presente l'ID del simulatore è meglio inserirlo
     * $outputResults: Per gli output diversi dal prezzo
     * $conditionalLogic: Quali campi devono essere nascosti e quali visualizzati
     * $errors: Se sono presenti degli errori vengono salvati qua. 
     *          Se viene impostato l'argomento, in caso di presenza di errori, 
     *          la funzione ritorna null
     * 
     */
    public function calculate_price($productId, $data, $formatPrice = true, $calculatorId = null, &$outputResults = null, &$conditionalLogic = null, $checkErrors = false, &$errors = null){
        $product        = $this->ecommerceHelper->getProductById($productId);
        
        if(empty($calculatorId)){
            $calculator = $this->get_simulator_for_product($productId);
        }else{
            $calculator = $this->calculatorModel->get($calculatorId);
        }
        
        $ret            = $this->calculate($calculator, $product, $data, $outputResults, $conditionalLogic, $checkErrors, $errors);
        
        /*WPC-PRO*/
        
        /* Accesso alle API */
        if($this->wsf->getLicense()== 1){  
            if($this->wsf->getTargetPlatform() == "wordpress"){
                $formatPriceFilter	= apply_filters('woo_price_calculator_format_price', $formatPrice, $ret, $product['id'], $calculator->id, $data);
                $ret                    = apply_filters('woo_price_calculator_calculate_price', $ret, $product['id'], $calculator->id, $data);
            }else if($this->wsf->getTargetPlatform() == "joomla"){
		$dispatcher = \JDispatcher::getInstance();
                
		$dispatcher->trigger('AwsPriceCalculatorFormatPrice', array(&$formatPriceFilter, $formatPrice, $ret, $product['id'], $calculator->id, $data));
                $dispatcher->trigger('AwsPriceCalculatorCalculatePrice', array(&$ret, $product['id'], $calculator->id, $data));
            }
            
            if($formatPriceFilter !== null){
                $formatPrice	= $formatPriceFilter;
            }
        }
        
        /*/WPC-PRO*/
        
        if($formatPrice == true){
            return $this->ecommerceHelper->get_price($ret);
        }else{
            return $ret;
        }
    }

    /*
     * Calcolo del prezzo utilizzando le formule inserite nel calculatore
     * 
     * $calculator: Il calcolatore (oggetto)
     * $product: Oggetto prodotto
     * $data: Sono i valori dei campi
     * $outputResults: Per gli output diversi dal prezzo
     * $conditionalLogic: Quali campi devono essere nascosti e quali visualizzati
     * $errors: Se sono presenti degli errori vengono salvati qua. 
     *          Se viene impostato l'argomento, in caso di presenza di errori, 
     *          la funzione ritorna null
     * 
     */
    public function calculate($calculator, $product, $data, &$outputResults = null, &$conditionalLogic = null, $checkErrors = false, &$errors = null){

        $ret                    = 0;
        $eos                    = new \jlawrence\eos\Parser();

        $inputFieldsIds         = $this->getCalculatorFields($calculator);
        $outputFieldsIds        = $this->getCalculatorFields($calculator, true);
        
        $fields                 = $this->fieldHelper->get_fields_by_ids($inputFieldsIds);
        $outputFields           = $this->fieldHelper->get_fields_by_ids($outputFieldsIds);  
        
        $formula                = $calculator->formula;
        
//        $vars                   = $this->calculateFieldsData($fields, $data, $product['price']);
        $userPrice = \csplite\WdmWuspGetData::getPriceOfProductForUser(get_current_user_id(), $product['id']);
        $vars                   = $this->calculateFieldsData($fields, $data, ((empty($userPrice) || $userPrice <= 0) ? $product['price'] : $userPrice));

        /*
         * Ordino in ordine decrescente di lunghezza le variabili, per esempio:
         * 
         * Array
            (
                [woo_price_calc_14] => 1500
                [woo_price_calc_1] => 12
                [woo_price_calc_5] => 100
                [woo_price_calc_6] => 21400
                [woo_price_calc_7] => 3300
                [price] => 23316
            )
         * 
         * In questo modo si andranno a sostituire da quella più lunga a quella più piccola,
         * perchè potrebbe accadere che parte di "woo_price_calc_14" venga erroneamente
         * sostituita da "woo_price_calc_1"
         */
        uksort($vars, create_function('$a,$b', 'return strlen($a) < strlen($b);'));

        /* $productImageLogic  = $this->calculateProductImageLogic($calculator, $vars); */
        $conditionalLogic   = $this->calculateConditionalLogic($calculator, $fields, $vars);
        
        foreach($vars as $var_key => $var_value){
            $fieldId                        = str_replace("aws_price_calc_", "", $var_key);
            $field                          = $this->fieldModel->get_field_by_id($fieldId);
            
            if(!empty($field)){
                $fieldOptions                   = json_decode($field->options, true);
            }
            
            $conditionalLogic[$fieldId]     = (isset($conditionalLogic[$fieldId])?$conditionalLogic[$fieldId]:1);
            
            if($conditionalLogic[$fieldId] == 0){
                if($field->type == "numeric"){
                        $value		= (empty($fieldOptions['numeric']['default_value'])?0:$fieldOptions['numeric']['default_value']);
                }else{
                        $value          = 0;
                }
				
            }else if(empty($var_value)){
                $value              = 0;
            }else{
                $value              = $var_value;
            }
            
            $vars[$var_key]         = $value;
            
            $formula = str_replace("\${$var_key}", (float)$value, $formula);
        }

        /* Verifico che sia impostato l'argomento $errors */
        if($checkErrors == true) {
            $errors                 = $this->checkErrors($fields, $vars, false, $product['id']);

            if(count($errors) != 0){
                return null;
            }
        }

        if($calculator->type == "simple" || empty($calculator->type)){

            try{
//                if (preg_match("/rabat\((.*)\)/", $formula) !== false) {
//                    //liczenie rabatu
//                    $metraz = $vars['aws_price_calc_1'] * $vars['aws_price_calc_2'] / 1000;
//                    if (preg_match("ceil", $formula) !== false) {
//                        $metraz = ceil($metraz);
//                    }
//                }




                //
                $ret    = $eos->solveIF($formula, $vars);
//                var_dump([
//                    'formula' => $formula,
//                    'vars' => $vars,
//                    'ret' => $ret,
//                ]);
            }catch(\Exception $ex){
                return $ex;
            }
            
        }else if($calculator->type == "excel"){
            /*WPC-PRO*/
            $loader_fields      = json_decode($calculator->options, true);
            $arr_fields         = $this->getLoaderCalculatorCells($calculator);

            $filePath       = $this->getSpreadsheetUploadPath($loader_fields['file']);
            $objReader      = \PHPExcel_IOFactory::createReader(\PHPExcel_IOFactory::identify($filePath));
            $objReader->setReadDataOnly(true);
            $objPHPExcel    = $objReader->load($filePath);
            $objWorksheet   = $objPHPExcel->setActiveSheetIndex($loader_fields['worksheet']);

            /* Imposto il prezzo base nelle celle selezionate dall'utente */
            if(!empty($loader_fields['price'])){
                $objPHPExcel->getActiveSheet()->setCellValue($loader_fields['price'], $product['price']);
            }
            
            foreach($vars as $field_id => $field_value){
                $field_key = str_replace("aws_price_calc_", "", $field_id);

                foreach($arr_fields as $coordinates => $fieldId){
                    if($fieldId == $field_key){
                        $objPHPExcel->getActiveSheet()->setCellValue($coordinates, $field_value);
                    }
                }
                
                
            }

            foreach($loader_fields['output'] as $coordinates => $outputFieldId){
                if($outputFieldId == 'price'){
                    $ret                                = $objPHPExcel->getActiveSheet()->getCell($coordinates)->getCalculatedValue();
                }else{
                    if(in_array($outputFieldId, $outputFieldsIds) ||
                       ($outputFieldId == $calculator->overwrite_weight)){
                        $outputResults[$outputFieldId]      = $objPHPExcel->getActiveSheet()->getCell($coordinates)->getCalculatedValue();
                    }
                }
            }
            
            /*/WPC-PRO*/
        }
        
        return $ret;
    }
    
    /* Funzione richiamata via Ajax per il calcolo in real-time del prezzo */
    public function calculatePriceAjax($action, $productId, $calculatorId, $cartItemKey = null, $quantity = null){
        $post               = $this->wsf->getPost();

        if($action == 'edit_cart_item'){
            if(!empty($productId) && !empty($calculatorId)){
                
                /* Calcolo i valori di output */
                $this->calculate_price($productId, $post, false, $calculatorId, $outputFieldsData);
                
                $cartData   = array(
                        'simulator_id'              => $calculatorId,
                        'simulator_fields_data'     => $post,
                        'output_fields_data'        => $outputFieldsData,
                );
                
                if($this->ecommerceHelper->getTargetEcommerce() == "woocommerce"){
                    global $woocommerce;

                    $woocommerce->cart->remove_cart_item($cartItemKey);
                    $woocommerce->cart->add_to_cart($productId, $quantity, 0, array(), $cartData);
                }else if($this->ecommerceHelper->getTargetEcommerce() == "hikashop"){
                    $cartClass          = \hikashop_get('class.cart');
                    $cart               = $cartClass->get(null);

                    $cart->cart_products[$cartItemKey]->awspricecalculator    = json_encode($cartData);

                    $cartClass->save($cart);
                }
            }
            
        }else{
            if(!empty($productId) && !empty($calculatorId)){
                $calculator             = $this->calculatorModel->get($calculatorId);
                $simulatorFieldsIds     = $this->get_simulator_fields($calculatorId);
                $fields                 = $this->fieldHelper->get_fields_by_ids($simulatorFieldsIds);
                $price                  = $this->calculate_price($productId, $post, true, $calculatorId, $outputResults, $conditionalLogic, true, $errors);
                $outputFields           = $this->getOutputResultsPart($calculator, $outputResults);

                die(json_encode(array(
                    'errorsCount'       => count($errors),
                    'errors'            => $errors,
                    'price'             => utf8_encode(htmlentities($price)),
                    'outputFields'      => $outputFields,
                    'conditionalLogic'  => $conditionalLogic,
                )));
            }
        }
        

        exit(-1);
    }
    
    /*
     * Ritorna il simulatore utilizzato per un prodotto
     */
    public function get_simulator_for_product($product_id){
        $simulators = $this->calculatorModel->get_list();

        /* Priorità ai singoli prodotti selezionati (Hanno precedenza) */
        foreach($simulators as $simulator){
            $products               = json_decode($simulator->products, true);
            
            /* Controllo se è stato selezionato quello specifico prodotto */
            if(!empty($products)){
                
                if(in_array($product_id, $products)){
                    return $simulator;
                }

            }
        }
        
        /* Dopo di chè valuto le categorie di prodotto */
        foreach($simulators as $simulator){

            $productCategories      = json_decode($simulator->product_categories, true);

            /* Controllo se è stata selezionata una categoria che contiene il prodotto */
            if(!empty($productCategories)){
                $terms      = get_the_terms($product_id, 'product_cat');
                $terms      = (empty($terms))?array():$terms;

                
                foreach ($terms as $term) {

                    if(in_array($term->term_id, $productCategories)){
                        return $simulator;
                    }

                    /* Controllo tutte le sottocategorie */
                    
                    foreach($productCategories as $productCategoryId){
                        if(term_is_ancestor_of($productCategoryId, $term->term_id, 'product_cat') == true){
                            return $simulator;
                        }
                    }
                }
                
            }
        
        }

        return null;
    }
    
    /*
     * Ritorna i campi utilizzati da un calcolatore
     */
    public function getCalculatorFields($calculator, $outputFields = false){
        if($outputFields === true){
            return json_decode($calculator->output_fields, true);
        }else{
            if($calculator->type == "simple" || empty($calculator->type)){
                return json_decode($calculator->fields, true);
            }else if($calculator->type == "excel"){
                /*WPC-PRO*/
                $ret = array();

                $fields         = json_decode($calculator->fields, true);

                //PMR: to supress a warning.
                if($fields === NULL){
                    return $ret; 
                }
                //
                foreach($fields as $coordinates => $fieldId){
                    if(!in_array($fieldId, $ret)){
                        $ret[] = $fieldId;
                    }
                }

                return $ret;
                /*/WPC-PRO*/
            }
        }
    }
    
    /*
     * Ritorna i campi utilizzati da un simulatore
     * 
     * OBSOLETA
     */
    public function get_simulator_fields($calculatorId, $outputFields = false){
        $calculator = $this->calculatorModel->get($calculatorId);

        return $this->getCalculatorFields($calculator, $outputFields);
    }
    
    /*
     * Ritorna i prodotti selezionati nel simulatore
     */
    public function get_simulator_products($simulator_id){
        $simulator = $this->calculatorModel->get($simulator_id);

        return json_decode($simulator->products);
    }
    
    /*
     * Ritorna i campi utilizzati da un simulatore
     */
    public function getLoaderCalculatorCells($calculator){
        $ret            = array();
        $loaderFields   = json_decode($calculator->options, true);
        
        //PMR: to supress a warning.
        if($loaderFields['input'] === NULL){
        	return $ret;
        }
        //
        foreach($loaderFields['input'] as $coordinates => $fieldId){
            $ret[$coordinates]      = $fieldId;
        }
        
        return $ret;
    }
        
    /*
     * Permette di scaricare un foglio di calcolo caricato
     */
    public function downloadSpreadsheet($simulatorId){
        
        /*
        if (!current_user_can('manage_options')){
            die("WPC: Access denied!");
        }
        */
        $calculator        = $this->calculatorModel->get($simulatorId);
        
        if($calculator->type == 'excel'){
            $calculatorOptions  = json_decode($calculator->options, true);
            $file               = $calculatorOptions['file'];
            $filename           = $calculatorOptions['filename'];
            $filePath           = $this->getSpreadsheetUploadPath($file);
            
            if(file_exists($filePath)){
                header('Content-Description: File Transfer');
                header('Content-Type: application/octet-stream');
                header("Content-Disposition: attachment; filename=\"{$filename}\"");
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Content-Length: ' . filesize($filePath));
                readfile($filePath);
                exit;
            }
        }
        
        die("WPC: Nothing to do");
    }
    
    /*
     * Ritorna il path del foglio di calcolo
     */
    public function getSpreadsheetUploadPath($file){
        return $this->wsf->getUploadPath("docs/{$file}");
    }
        
    /*
     * Controlla gli errori del simulatore
     */
    public function checkErrors($fields, $fieldValues = array(), $calculateFieldsData = false, $product){
        
        $errors                         = array();

        if($calculateFieldsData == true){
            $calculatedValues       = $this->calculateFieldsData($fields, $fieldValues);
        }

        foreach($fields as $field_key => $field_value){
            $fieldId                            = "{$this->wsf->getPluginShortCode()}_{$field_value->id}";
            $options                            = json_decode($field_value->options, true);

            if($calculateFieldsData == false){
                $value                              = (isset($fieldValues[$fieldId]))?$fieldValues[$fieldId]:null;
            }else{
                $value                              = $calculatedValues[$fieldId];
            }
            
            /* CAMPO OBBLIGATORIO? */
            if($field_value->required == true){
                if($value === "" || $value === 0 || $value === "0"){
                    
                    /* Visualizzazione di un messaggio di default */
                    if(empty($field_value->required_error_message)){
                        $errors[$fieldId][]       = $this->wsf->trans('aws.field.error_message.required_error_message', array(
                            'fieldLabel'    => $field_value->label
                        ));
                    }else{
                        $errors[$fieldId][]       = $this->wsf->userTrans($field_value->required_error_message, array(
                            'fieldLabel'    => $field_value->label
                        ));
                    }
                }
            }
            
            /* CONTROLLO DATI */
            if($field_value->type == "text"){
                if(!empty($options['text']['regex'])){
                    preg_match($options['text']['regex'], $value, $matches);

                    if(count($matches) == 0){

                        /* Visualizzazione di un messaggio di default */
                        if(empty($options['text']['regex_error'])){
                            $errors[$fieldId][]       = $this->wsf->trans('aws.field.error_message.regex_error', array(
                                'fieldLabel'    => $field_value->label
                            ));
                        }else{
                            $errors[$fieldId][]       = $this->wsf->userTrans($options['text']['regex_error'], array(
                                'fieldLabel'    => $field_value->label
                            ));
                        }
                        
                    }
                }
            }else if($field_value->type == "numeric"){
                if(!empty($options['numeric']['max_value'])){
                    if($value > $options['numeric']['max_value']){
                        
                        /* Visualizzazione di un messaggio di default */
                        if(empty($options['numeric']['max_value_error'])){
                            $errors[$fieldId][]       = $this->wsf->trans('aws.field.error_message.max_value_error', array(
                                'fieldLabel'    => $field_value->label,
                                'maxValue'      => $options['numeric']['max_value'],
                            ));
                        }else{
                            $errors[$fieldId][]       = $this->wsf->userTrans($options['numeric']['max_value_error'], array(
                                'fieldLabel'    => $field_value->label,
                                'maxValue'      => $options['numeric']['max_value'],
                            ));
                        }

                    }
                }

                $_pf = new \WC_Product_Factory();
                $_product = $_pf->get_product($product);

                $maxWymiar = $_product->get_attribute( 'maksymalny_wymiar' );
                if (isset($fieldValues['aws_price_calc_1']) && isset($fieldValues['aws_price_calc_2']) && $maxWymiar) {
                    $errorMax = false;

                    if ($fieldValues['aws_price_calc_1'] > $maxWymiar && $fieldValues['aws_price_calc_2'] > $maxWymiar) {
                        $errorMax = true;
                    }
//                    if ($fieldValues['aws_price_calc_2'] > $maxWymiar) {
//                        $errorMax = true;
//                    }

                    if ($errorMax) {
                        if ($fieldId !== 'aws_price_calc_37') {
                            $errors[$fieldId][]       = 'Jeden z  dwóch wymiarów nie może przekraczać ' . $maxWymiar;
                        }
                    }
                }

                if(!empty($options['numeric']['min_value'])){
                    if($value < $options['numeric']['min_value']){
                        
                        /* Visualizzazione di un messaggio di default */
                        if(empty($options['numeric']['min_value_error'])){
                            $errors[$fieldId][]       = $this->wsf->trans('aws.field.error_message.min_value_error', array(
                                'fieldLabel'    => $field_value->label,
                                'minValue'      => $options['numeric']['min_value'],
                            ));
                        }else{
                            $errors[$fieldId][]       = $this->wsf->userTrans($options['numeric']['min_value_error'], array(
                                'fieldLabel'    => $field_value->label,
                                'minValue'      => $options['numeric']['min_value'],
                            ));
                        }

                    }
                }
            }
        }
        
        return $errors;
    }
    
    /*
     * Controlla che se il prezzo nei prodotti selezionati è nullo, visualizza
     * un messaggio di avvertimento che dice che per visualizzare il simulatore
     * è necessario inserire un prezzo
     */
    public function checkProductPrices($productIds){
        $warnings   = array();
        
        foreach($productIds as $productId){
            $product            = $this->ecommerceHelper->getProductById($productId);
            $price              = $product['price'];
            $title              = $product['name'];
            
            if($price == ''){
                $warnings[]         = $this->wsf->trans("wpc.calculator.form.price.warning", array(
                    'productTitle'      => $title,
                ));
            }

        }

        return $warnings;
    }
    
    /*
     * Ritorna la lista dei campi ordinata per selezione da parte dell'utente
     */
    public function orderFields($selectedFields, $mode = ''){
        $orderedFields      = array();
        $fields             = $this->fieldModel->get_field_list($mode);
        
        foreach($selectedFields as $fieldId){
            $orderedFields[]        = $this->fieldModel->get_field_by_id($fieldId);
        }

        foreach($fields as $field){
            if(!in_array($field, $orderedFields)){
                $orderedFields[]    = $field;
            }
        }
        
        return $orderedFields;
    }
    
    /*
     * Controlla che due o più calcolatori non siano assegnati allo stesso prodotto
     */
    /*
     * TODO: Verificare il corretto funzionamento sia con Joomla che con Wordpress
     */
    public function checkCalculatorDuplicate($record, $excludeId){
        $errors           = array();
        
        $check_simulators                   = $this->calculatorModel->get_list();
        $productCategories                  = $this->ecommerceHelper->getProductCategories();
        
        $checkCalculatorProducts            = array();
        $checkCalculatorProductCategories   = array();
        
        /* Faccio la lista di tutti i prodotti utilizzati */
        foreach($check_simulators as $check_simulator){
            if($check_simulator->id != $excludeId){
                
                $calculatorProducts                 = json_decode($this->wsf->isset_or($check_simulator->products, "{}"), true);
                $calculatorProductCategories        = json_decode($this->wsf->isset_or($check_simulator->product_categories, "{}"), true);
                
                foreach($calculatorProductCategories as $productCategoryId){
                    $checkCalculatorProductCategories = array_merge($checkCalculatorProductCategories, $this->ecommerceHelper->getCategoryProductsByCategoryId($productCategoryId));
                }
                
                $checkCalculatorProducts            = array_merge($checkCalculatorProducts, $calculatorProducts);
            }
        }

        $checkCalculatorProducts            = array_unique($checkCalculatorProducts);
        $checkCalculatorProductCategories   = array_unique($checkCalculatorProductCategories);
        
        /* END */
               
        
        /*
         * Controllo se fra i prodotti e le categorie selezionate del simulatore
         * ci sono simulatori utilizzati
         */
        if(!empty($checkCalculatorProducts) || !empty($checkCalculatorProductCategories)){

            $checkReqProducts               = $record['products'];
            $checkReqProductCategories      = array();
            
            foreach($record['product_categories'] as $productCategoryId){
                $checkReqProductCategories = array_merge($checkReqProductCategories, $this->ecommerceHelper->getCategoryProductsByCategoryId($productCategoryId));
            }
            
            $checkReqProducts           = array_unique($checkReqProducts);
            $checkReqProductCategories  = array_unique($checkReqProductCategories);
            
            if(!empty($checkReqProducts) || !empty($checkReqProductCategories)){
                
                /* Controllo dei prodotti */
                foreach($checkReqProducts as $check_req_product){
                    if(in_array($check_req_product, $checkCalculatorProducts)){
                        $check_product      = $this->ecommerceHelper->getProductById($check_req_product);
                        $checkProductTitle  = $check_product['name'];
                        
                        $errors[]           = $this->wsf->trans("calculator.form.error.product_duplicate", array(
                            'productTitle'   => $checkProductTitle,
                        ));
                    }
                }
                
                /* Controllo dei prodotti delle categorie */
                foreach($checkReqProductCategories as $check_req_product){
                    if(in_array($check_req_product, $checkCalculatorProductCategories)){

                        $errors[]           = $this->wsf->trans("calculator.form.error.categories_duplicate");
                        
                        break;
                    }
                }
            }
        }
        
        return $errors;
    }
    
    /*
     * Prende i valori dei campi inseriti dal visitatori
     */
    public function getFieldsFromRequest($simulatorId, $calculateFieldsData = false){
        $simulator_fields_ids   = $this->get_simulator_fields($simulatorId);
        
        $fields                 = $this->fieldHelper->get_fields_by_ids($simulator_fields_ids);
        $fieldsData             = array();

        foreach($fields as $field_key => $field_value){
            $fieldRequestKey                    = "aws_price_calc_{$field_value->id}";
            $options                            = json_decode($field_value->options, true);
            $value                              = $this->wsf->requestValue($fieldRequestKey);

            /* AGGIUSTO I VALORI */
            $fieldsData[$fieldRequestKey] = $value;
        }
        
        if($calculateFieldsData == true){
            $fieldsData     = $this->calculateFieldsData($fields, $fieldsData);
        }
        
        return array(
            'fields'            => $fields,
            'data'              => $fieldsData,
        );
    }
     
    /*
     * Calculate the final value for each field
     */
    public function calculateFieldsData($fields, $data, $productPrice = null){
        
        $vars                   = array();
        
        if($productPrice !== null){
            $vars['price']      = $productPrice;
        }

        foreach($fields as $field_key => $field_value){
            if(!empty($field_value)){
                $options    = json_decode($field_value->options, true);
                $fieldId    = $this->wsf->getPluginShortCode() . '_' . $field_value->id;
                $value      = $this->wsf->isset_or($data[$fieldId], 0);

                if($field_value->type == "checkbox"){
                    if($value === "on" || $value == 1){
                        $value = $options['checkbox']['check_value'];
                    }else{
                        $value = $options['checkbox']['uncheck_value'];
                    }
                }else if($field_value->type == "numeric"){
                    $value = str_replace($options['numeric']['decimal_separator'], ".", $value);
                    
                    //Se il campo è vuoto definisco 0
                    if(empty($value)){
                        $value  = 0;
                    }
                    
                }else if($field_value->type == "radio"){
                    $itemsData         = json_decode($options['radio']['radio_items'], true);
                    
                    if(!empty($itemsData)){
                        foreach($itemsData as $index => $item){
                            if($item['id'] == $value){
                                $value  = $item['value'];
                                break;
                            }
                        }
                    }
                }else if($field_value->type == "imagelist"){
                    $itemsData         = json_decode($options['imagelist']['imagelist_items'], true);
                    
                    if(!empty($itemsData)){
                        foreach($itemsData as $index => $item){
                            if($item['id'] == $value){
                                $value  = $item['value'];
                                break;
                            }
                        }
                    }
                }else if($field_value->type == "picklist"){
                    $itemsData         = json_decode($options['picklist_items'], true);

                    if(!empty($itemsData)){
                        foreach($itemsData as $index => $item){
                            if($item['id'] == $value){
                                $value  = $item['value'];
                                break;
                            }
                        }
                    }
                    
                }else if($field_value->type == "text"){
                    $value      = $data[$fieldId];
                }else if($field_value->type == "date" ||
                         $field_value->type == "time" ||
                         $field_value->type == "datetime"){
                    
                    $value  = 0;
                    
                }else{
                    throw new \Exception("CalculatorHelper::calculateFieldsData field type {$field_value->type} not supported!");
                }

                $vars[$this->wsf->getPluginShortCode() . '_' . $field_value->id] = $value;
            }
        }
        
        
        return $vars;
    }
            
    /* Calculate what field should be displayed or hidden */
    public function calculateConditionalLogic($calculator, $calculatorFields, $fieldValues){

        $calculatorConditionalLogic     = json_decode($calculator->conditional_logic, true);
        $conditionalFieldsLogic         = array();

        /*WPC-PRO*/
        if($this->wsf->getLicense() == 1){
            if($calculatorConditionalLogic['enabled'] == true){
                $hideFields         = (empty($calculatorConditionalLogic['hide_fields']))?array():$calculatorConditionalLogic['hide_fields'];
                $fieldFiltersSql    = $calculatorConditionalLogic['field_filters_sql'];

                foreach($calculatorFields as $calculatorField){
                    $calculatorFieldId     = $calculatorField->id;

                    $displayField          = false;
                    if(!in_array($calculatorFieldId, $hideFields)){
                        $displayField   = true;
                    }

                    $displayField           = (int)$displayField;
                    $inverseDisplayField    = (int)!$displayField;

                    $fieldFiltersSql[$calculatorFieldId]            = str_replace('aws_price_calc_', ':aws_price_calc_', $fieldFiltersSql[$calculatorFieldId]);

                    //echo "{$calculatorFieldId}: SELECT IF({$fieldFiltersSql[$calculatorFieldId]}, {$displayField}, {$inverseDisplayField}) AS query_result\n";

                    if(!empty($fieldFiltersSql[$calculatorFieldId])){
                        $queryCalculateConditionalLogic                 = $this->databaseHelper->getRow("SELECT IF({$fieldFiltersSql[$calculatorFieldId]}, {$inverseDisplayField}, {$displayField}) AS query_result", $fieldValues);
                        $conditionalFieldsLogic[$calculatorFieldId]     = $queryCalculateConditionalLogic->query_result;
                    }else{
                        $conditionalFieldsLogic[$calculatorFieldId]     = $displayField;
                    }

                }
            }else{
                foreach($calculatorFields as $calculatorField){
                    $conditionalFieldsLogic[$calculatorField->id] = true;
                }
            }
        }
        /*/WPC-PRO*/

        return $conditionalFieldsLogic;
    }
    
    /*
     * Ritorna i valori di default di tutti i campi di un calcolatore
     */
    public function getFieldsDefaultValue($calculatorId){
        $simulatorFieldsIds                     = $this->get_simulator_fields($calculatorId);
        return $this->getFieldsDefaultValueByFieldIds($simulatorFieldsIds);
    }
    
    /*
     * Ritorna i valori di default dagli ID dei singoli campi
     */
    public function getFieldsDefaultValueByFieldIds($fieldIds){
        $simulatorFields                        = $this->fieldHelper->get_fields_by_ids($fieldIds);
        $fieldsData                             = array();
        
        foreach($simulatorFields as $fieldKey => $field){
            if(!empty($field)){
                $fieldId    			= $this->wsf->getPluginShortCode() . '_' . $field->id;
                $defaultValue			= $this->fieldHelper->getFieldDefaultValue($field);

                $fieldsData[$fieldId]	= $defaultValue;
            }
        }
        
        return $fieldsData;
    }
    
    /*
     * TODO
     * Ritorna i valori dei campi che dovrebbe avere il calcolatore, 
     * se ancora non sono stati inseriti dal visitatore nessun parametro
     */
    public function getStartupFieldValues($calculatorId){
        $simulatorFieldsIds                     = $this->get_simulator_fields($calculatorId);
        $simulatorFields                        = $this->fieldHelper->get_fields_by_ids($simulatorFieldsIds);
        $fieldsData				= array();

        foreach($simulatorFields as $fieldKey => $field){
            if(!empty($field)){
                $fieldId    			= $this->wsf->getPluginShortCode() . '_' . $field->id;
                $fieldOptions                   = json_decode($field->options, true);

                $startupValue			= $this->fieldHelper->getFieldDefaultValue($field);
                
                if($field->type == 'numeric'){
                    if(!empty($fieldOptions['numeric']['min_value']))
                        $startupValue               = $fieldOptions['numeric']['min_value'];
                }

                $fieldsData[$fieldId]	= $startupValue;
            }
        }
        
        return $fieldsData;
    }
        
    /*
     * Stampa i campi di output
     */
    public function getOutputResultsPart($calculator, $outputResults){
        $calculatorOutputFields = json_decode($calculator->output_fields, true);
                        
        $outputFields   = "";
        
        if(!empty($outputResults)){
            foreach($outputResults as $fieldId => $fieldResult){
                if(in_array($fieldId, $calculatorOutputFields)){
                    $field                          = $this->fieldModel->get_field_by_id($fieldId);
                    $fieldOptions                   = json_decode($field->options, true);

                    $decimals                       = (int)$fieldOptions['numeric']['decimals'];
                    $decimalSeparator               = $fieldOptions['numeric']['decimal_separator'];
                    $thousandSeparator              = $fieldOptions['numeric']['thousand_separator'];

                    if(is_numeric($fieldResult)){
                        $value                          = number_format($fieldResult, $decimals, $decimalSeparator, $thousandSeparator);
                    }else{
                        $value                          = $fieldResult;
                    }

                    $outputFields                   .= "<b>{$field->label}</b>: {$value}<br/>";
                }
            }
        }
        
        return $outputFields;
    }    
    
    /*
     * Genera il nome del file random per i fogli Excel
     */
    public function generateFileName(){
       return md5('unique_salt' . time()); 
    }
    
    /*
     * Aggiorna i filtri Json del conditional logic con nuovi ID dei campi
     */
    public function updateConditionalLogicJsonFilters(&$item, $key, $params){
        $fieldMappingIds        = $params['fieldMappingIds'];
  
        if($key == 'id'){
            $item       = $fieldMappingIds[$item];
        }else if($key == 'field'){
            $fieldId    = str_replace("aws_price_calc_", "", $item);
            $item       = "aws_price_calc_{$fieldMappingIds[$fieldId]}";
        }

    }
    
    /*
     * Aggiorna i filtri Sql del conditional logic con nuovi ID dei campi
     */
    public function updateConditionalLogicSqlFilters($sqlFieldFilters, $fieldMappingIds){
        $retSqlFieldFilters     = array();
        
        /* Evita bug relativo alla sostituzione */
        foreach($sqlFieldFilters as $fieldId => $sqlFieldFilter){
            $sqlFieldFilters[$fieldId]      = str_replace("aws_price_calc_", "tmp_", $sqlFieldFilter);
        }
        
        /* Sostituisco in ogni singolo filtro l'ID del vecchio campo con quello del nuovo */
        foreach($sqlFieldFilters as $fieldId => $sqlFieldFilter){
            $newFieldFilterId                         = $fieldMappingIds[$fieldId];
            $retSqlFieldFilters[$newFieldFilterId]    = $sqlFieldFilter;
            
            foreach($fieldMappingIds as $oldFieldMappingId => $newFieldMappingId){
                $retSqlFieldFilters[$newFieldFilterId]    = str_replace("tmp_{$oldFieldMappingId}", "aws_price_calc_{$newFieldMappingId}", $retSqlFieldFilters[$newFieldFilterId]);
            }
            

        }
        
        return $retSqlFieldFilters;
    }
    
    /* Controlla che lo ZIP di importazione sia compatibile con la versione attuale */
    public function checkImportZipVersion($filePath){
        $zip                 = zip_open($filePath);
        
        $versionFileFound   = false;
        $currentVersion     = $this->wsf->getVersion();
        $zipVersion         = null;
        $ret                = '=';
        
        if(is_resource($zip)){
            while($entry = zip_read($zip)){
                $path           = zip_entry_name($entry);

                if($path    == "version.data"){
                    $zipVersion           = trim(zip_entry_read($entry, zip_entry_filesize($entry)));
                    $versionFileFound     = true;
                    
                    if(version_compare($currentVersion, $zipVersion, '>')){
                        $ret    = '>';
                    }else if(version_compare($currentVersion, $zipVersion, '<')){
                        $ret    = '<';
                    }
                }

            }

            zip_close($zip);

            if($versionFileFound == false){
                return false;
            }
            
            return array(
                'currentVersion'    => $currentVersion,
                'zipVersion'        => $zipVersion,
                'comparison'        => $ret,           
            );
        }

        return false;

    }
    
    /*
     * Carica lo ZIP per l'importazione inserendo in memoria i calcolatori, campi
     * e mapping dei documenti. Si occupa anche di spostare in /docs i documenti
     * con un nuovo nome
     */
    public function loadZip($filePath, &$calculators, &$fields, &$docsMapping, &$themes){
        $zip                 = zip_open($filePath);
        $calculators         = array();
        $fields              = array();
        $themes              = array();
        $docsMapping         = array();

        if(is_resource($zip)){
                        
            while($entry = zip_read($zip)){
                $path           = zip_entry_name($entry);
                $directory      = dirname($path);
                $filename       = basename($path);
                
                $read           = zip_entry_read($entry, zip_entry_filesize($entry));

                if($directory == "calculators"){
                    $calculatorId                    = basename($path, ".json");
                    $calculators[$calculatorId]      = json_decode($read, true);
                }else if($directory == "fields"){
                    $fieldId                         = basename($path, ".json");
                    $fields[$fieldId]                = json_decode($read, true);
                    $fields[$fieldId]['options']     = json_decode($fields[$fieldId]['options'], true);
                }else if($directory == "docs"){
                    /* Copio il file Excel dallo ZIP alla cartella dei documenti */
                    $spreadsheetFileName        = $this->generateFileName();
                    $docsMapping[$filename]     = $spreadsheetFileName;
                    
                    $spreadsheetPath            = $this->wsf->getUploadPath("docs/{$spreadsheetFileName}");
                    file_put_contents($spreadsheetPath, $read);
                }else if($directory == "themes"){
                    $themeFileName              = basename($path, ".php");
                    
                    $themes[$themeFileName]     = $read;
                }
            }
            
            zip_close($zip);
            
            return true;
            
        } else {
            return false;
        }
    }
    
    /* 
     * Caricamento dei campi
     * 
     * $fields              Lista dei campi da importare
     * &$fieldMapping       Salvo il mapping risultato dall'importazione nel formato
     *                      [input/output][vecchio ID] = [nuovo ID]
     * &$fieldMappingIds    Simile al precedente ma non tengo conto dell'input/output
     *                      [vecchio ID] = [nuovo ID]
     * &$newFields          Salvo i campi che sono stati creati
     * &$mappedFields       Salvo i campi che non sono stati creati, ma che sono stati
     *                      solo mappati
     */
    public function importFields($fields, &$fieldMapping, &$fieldMappingIds, &$newFields, &$mappedFields){
        
        $newFields           = array();
        $mappedFields        = array();
        
        $fieldMappingIds     = array();
        $fieldMapping        = array(
            'input'     => array(),
            'output'    => array(),
        );
        
        foreach($fields as $fieldId => $field){
            $findField      = $this->fieldHelper->findField($field);

            if($findField == false){
                /* Il campo non è stato trovato, quindi ne devo creare uno nuovo */
                $field['id']                                = null;
                $newFieldId                                 = $this->fieldModel->save($field);

                $fieldMapping[$field['mode']][$fieldId]     = $newFieldId;
                $fieldMappingIds[$fieldId]                  = $newFieldId;

                $newFields[]                                = $field;     
            }else{
                $fieldMapping[$field['mode']][$fieldId]     = $findField->id;
                $fieldMappingIds[$fieldId]                  = $findField->id;

                $mappedFields[]                             = $findField;
            }
        }

        /* Ordino in ordine decrescente di lunghezza le variabili, 
         * sarà utile per evitare bug nelle sostituzioni 
         */
        uksort($fieldMappingIds, create_function('$a,$b', 'return strlen($a) < strlen($b);'));
    }
    
    /*
     * Importa i temi convertendo eventuali ID dei campi
     * 
     * $themes              Lista dei temi nel formato
     *                      [Nome tema] => [Contenuto file]
     * 
     * $fieldMappingIds     $fieldMappingIds ottenuto dall' importFields
     * &$themesMapping      Risultato dell'importazione
     */
    public function importThemes($themes, $fieldMappingIds, &$themesMapping){
        
        $themesMapping       = array(
            'all'       => array(),
            'created'   => array(),
            'mapped'    => array(),
        );            
        
        /* Converto gli ID dei campi contenuti all'interno del tema */
        foreach($themes as $themeFileName => $themeContent){
            /* Evito bug dovuti a sostituzione */
            $themeContent       = str_replace("aws_price_calc_", "#tmp_price_calc_", $themeContent);
                   
            foreach($fieldMappingIds as $oldFieldId => $newFieldId){
                $themeContent       = str_replace("#tmp_price_calc_{$oldFieldId}", "aws_price_calc_{$newFieldId}", $themeContent);
            }
            
            /* Sostituisco eventuali stringhe che non sono state sostituite (Magari erano senza ID, classi CSS, ecc) */
            $themeContent       = str_replace("#tmp_price_calc_", "aws_price_calc_", $themeContent);
            
            $theme                = $this->themeHelper->findTheme($themeContent);
            $date                 = date('Ymd_His');
            $filename             = "{$themeFileName}.php";
            
            if($theme == false){
                $newThemeFileName                       = "{$themeFileName}_{$date}.php";
                $themePath                              = $this->wsf->getUploadPath("themes/{$newThemeFileName}");

                $themesMapping['all'][$filename]        = $newThemeFileName;
                $themesMapping['created'][$filename]    = $themesMapping['all'][$filename];

                file_put_contents($themePath, $themeContent);
            }else{
                $themesMapping['all'][$filename]       = $theme['filename'];
                $themesMapping['mapped'][$filename]    = $themesMapping['all'][$filename];
            }
        }
    }

    /* 
     * Importa un calcolatore 
     * 
     * $filePath    = ZIP file
     */
    public function import($filePath){

        if($this->loadZip($filePath, $calculators, $fields, $docsMapping, $themes)){

            $this->importFields($fields, $fieldMapping, $fieldMappingIds, $newFields, $mappedFields);
            $this->importThemes($themes, $fieldMappingIds, $themesMapping);
            
            /* Caricamento dei calcolatori */
            foreach($calculators as $calculatorIndex => $calculator){
               $calculator['id']                     = null;
               $calculator['system_created']         = 0;
               $calculator["fields"]                 = $fieldMapping['input'];
               $calculator["output_fields"]          = $fieldMapping['output'];
               $calculator["products"]               = array();
               $calculator["product_categories"]     = array();
               $calculator["options"]                = json_decode($calculator['options'], true);
               $calculator["conditional_logic"]      = json_decode($calculator['conditional_logic'], true);

               /* Conversione Overwrite Weight */
               if(!empty($calculator['overwrite_weight'])){
                   $calculator['overwrite_weight']   = $fieldMappingIds[$calculator['overwrite_weight']];
               }
               
               /* Converto i temi */
               if(!empty($calculator['theme'])){
                   $calculator['theme']                  = $themesMapping['all'][$calculator['theme']];
               }
               
               if($calculator['type'] == 'excel'){
                    /*WPC-PRO*/
                    $calculator['options']['file']   = $docsMapping[$calculator['options']['file']];

                    /* Conversione dei campi di mapping di input */
                    foreach($calculator['options']['input'] as $cell => $fieldId){
                        if(is_numeric($fieldId)){
                            $calculator['options']['input'][$cell]  = $fieldMappingIds[$fieldId];
                        }
                    }
                    
                    /* Conversione dei campi di mapping di output */
                    foreach($calculator['options']['output'] as $cell => $fieldId){
                        if(is_numeric($fieldId)){
                            $calculator['options']['output'][$cell]  = $fieldMappingIds[$fieldId];
                        }
                    }
                    /*/WPC-PRO*/
               }else{
                   /* Converto eventuali campi */
                   /* Ordino in ordine decrescente di lunghezza le variabili */
                   uksort($fields, create_function('$a,$b', 'return strlen($a) < strlen($b);'));
                   
                   /* Evito bug dovuti a sostituzione */
                   $calculator['formula']       = str_replace("\$aws_price_calc_", "tmp_", $calculator['formula']);
                   
                   /* Sostituisco le variabili della formula */
                   foreach($fields as $fieldId => $field){
                       $newFieldId              = $fieldMapping[$field['mode']][$fieldId];
                       $calculator['formula']   = str_replace("tmp_{$fieldId}", "\$aws_price_calc_{$newFieldId}", $calculator['formula']);
                   }
                   
               }
               
               /* Salvo il calcolatore nel database */
               $calculators[$calculatorIndex]['id']  = $this->calculatorModel->save($calculator);
               
               /* Caricamento del Conditional Logic */
               if(!empty($calculator["conditional_logic"])){
                   $conditionLogic      = &$calculator["conditional_logic"];
                   $fieldFiltersJson    = array();
                   
                   foreach($conditionLogic['hide_fields'] as $hideFieldIndex => $hideField){
                       $conditionLogic['hide_fields'][$hideFieldIndex]      = $fieldMappingIds[$hideField];
                   }
                   
                   foreach($conditionLogic['field_filters_json'] as $oldFieldId => $oldFieldFiltersJson){
                       $newFieldId                      = $fieldMappingIds[$oldFieldId];
                       
                       $fieldFiltersJson[$newFieldId]   = $oldFieldFiltersJson;
                   }
                   
                   array_walk_recursive($fieldFiltersJson, array($this, 'updateConditionalLogicJsonFilters'), array(
                       'fieldMappingIds'    => $fieldMappingIds,
                   ));
                           
                   $conditionLogic['field_filters_json']    = $fieldFiltersJson;
                   $conditionLogic['field_filters_sql']     = $this->updateConditionalLogicSqlFilters($conditionLogic['field_filters_sql'], $fieldMappingIds);
                   
                   $this->calculatorModel->saveConditionalLogic($conditionLogic, $calculators[$calculatorIndex]['id']);
                   
               }              
               
            }

            return array(
                'newFields'     => $newFields,
                'mappedFields'  => $mappedFields,
                'calculators'   => $calculators,
                'themesMapping' => $themesMapping,
            );
        }else{
            return false;
        }
    }
    
    /* 
     * Effettua l'export di un calcolatore 
     * $calculator  = Calcolatore da esportare
     * $filename    = Nome del file ZIP
     */
    
    public function export($calculator, $filename){
        $id                         = $calculator->id;
        $version                    = $this->wsf->getVersion();
        $zip                        = new \ZipArchive();
        $tmpDir                     = sys_get_temp_dir();
        $filePath                   = "{$tmpDir}/{$filename}";
        
        $calculatorOptions          = json_decode($calculator->options, true);
        
        $calculatorInputFields      = json_decode($calculator->fields, true);
        $calculatorOutputFields     = json_decode($calculator->output_fields, true);
        $mappingInputFields         = array();
        $mappingOutputFields        = array();
        
        if($calculator->type == 'excel'){
            $spreadsheetPath            = $this->wsf->getUploadPath("docs/{$calculatorOptions['file']}");
            
            /* Se è un file Excel devo copiare sia i campi selezionati, che quelli mappati */
            $mappingInputFields         = array_values($calculatorOptions['input']);
            $mappingOutputFields        = array_values($calculatorOptions['output']);

        }
        
        $fields                     = array_merge(
                $calculatorInputFields, 
                $calculatorOutputFields,
                $mappingInputFields,
                $mappingOutputFields
        );

        /* Verifico se il file temp esiste già, se si, lo cancello */
        if(file_exists($filePath)){
            unlink($filePath);
        }
        
        if($zip->open($filePath, \ZipArchive::CREATE) !== TRUE){
            return false;
        }

        $zip->addFromString("version.data", $version);
        $zip->addFromString("calculators/{$id}.json", json_encode($calculator));

        foreach($fields as $fieldId){
            $field      = $this->fieldModel->get_field_by_id($fieldId);
            
            if(!empty($field)){
                $zip->addFromString("fields/{$fieldId}.json", json_encode($field));
            }
        }
        
        if($calculator->type == "excel"){
            $zip->addFile($spreadsheetPath, "docs/{$calculatorOptions['file']}");
        }
        
        if(!empty($calculator->theme)){
            $themePath            = $this->wsf->getUploadPath("themes/{$calculator->theme}");
            
            $zip->addFile($themePath, "themes/{$calculator->theme}");
        }
        
        $zip->close();

        return $filePath;
    }
}
