<?php
/**
 * @package AWS Price Calculator
 * @author Enrico Venezia
 * @copyright (C) Altos Web Solutions Italia
 * @license GNU/GPL v2 http://www.gnu.org/licenses/gpl-2.0.html
**/

namespace WSF\Controller;

/*AWS_PHP_HEADER*/

use WSF\Helper\FrameworkHelper;

class CalculatorController {
    private $wsf;
    private $db;
   
    private $tableHelper;
    private $calculatorHelper;

    private $fieldModel;
    private $calculatorModel;
   
    private $wooCommerceHelper;
   
    public function __construct(FrameworkHelper $wsf){
        if(!session_id()){
            session_start();
        }
       
        $this->wsf  = $wsf;
       
        $this->tableHelper          = $this->wsf->get('\\WSF\\Helper', true, 'awspricecalculator/Helper', 'TableHelper', array($this->wsf));
       
        /* MODELS */
        $this->fieldModel               = $this->wsf->get('\\WSF\\Model', true, 'awspricecalculator/Model', 'FieldModel', array($this->wsf));
        $this->calculatorModel          = $this->wsf->get('\\WSF\\Model', true, 'awspricecalculator/Model', 'CalculatorModel', array($this->wsf));
       
        /* HELPERS */
        $this->themeHelper          = $this->wsf->get('\\WSF\\Helper', true, 'awspricecalculator/Helper', 'ThemeHelper', array($this->wsf));
        $this->calculatorHelper     = $this->wsf->get('\\WSF\\Helper', true, 'awspricecalculator/Helper', 'CalculatorHelper', array($this->wsf));
        $this->wooCommerceHelper    = $this->wsf->get('\\WSF\\Helper', true, 'awsframework/Helper', 'EcommerceHelper', array($this->wsf));
        $this->fieldHelper          = $this->wsf->get('\\WSF\\Helper', true, 'awspricecalculator/Helper', 'FieldHelper', array($this->wsf));
        $this->pluginHelper         = $this->wsf->get('\\WSF\\Helper', true, 'awspricecalculator/Helper', 'PluginHelper', array($wsf));
       
        $this->MAX_EMPTY_COLUMNS    = 100;
        $this->MAX_EMPTY_ROWS       = 50;
    }
   
    public function indexAction(){
        $this->wsf->execute('awspricecalculator', true, 'index', 'index');
       
        if($this->wsf->getLicense() != 0){
            $loadCalculatorUrl      = $this->wsf->adminUrl(array('controller' => 'calculator', 'action' => 'loader'));
        }else{
            $loadCalculatorUrl      = "#load_calculator";
        }
       
        $this->wsf->renderView('calculator/list.php', array(
            'list_header'    => array(
                'name'              => $this->wsf->trans('wpc.calculator.list.name'),
                'description'       => $this->wsf->trans('wpc.calculator.list.description'),
                'type'              => $this->wsf->trans('wpc.calculator.list.type'),
                'actions'           => $this->wsf->trans('wpc.actions'),
            ),
            'list_rows'             => $this->calculatorModel->get_list(),
           
            'loadCalculatorUrl'     => $loadCalculatorUrl,
        ));
       
       
    }
   
    public function loaderAction(){
        $setToken       = $this->wsf->requestValue('set_token');
        $calculatorId   = $this->wsf->requestValue('calculator_id');
       
        $this->wsf->execute('awspricecalculator', true, 'index', 'index');

        if(!empty($setToken)){
            $token      = $setToken;
        }else{
            $token      = $this->calculatorHelper->generateFileName();
        }
       
        $this->wsf->renderView('calculator/load.php', array(
            'calculator_id' => $calculatorId,
            'token'         => $token,
        ));

    }
   
    /*WPC-PRO*/
    public function loaderSheetAction(){
        $_SESSION['woo-price-calculator']['admin']['file']          = null;
        $_SESSION['woo-price-calculator']['admin']['filename']      = null;
        $_SESSION['woo-price-calculator']['admin']['worksheet']     = null;
       
        $this->wsf->execute('awspricecalculator', true, 'index', 'index');
       
        $file           = $this->wsf->requestValue('file');
        $filename       = $this->wsf->requestValue('filename');
        $calculatorId   = $this->wsf->requestValue('calculator_id');
               
        $filePath       = $this->wsf->getUploadPath('docs/' . $file);
        $objReader      = \PHPExcel_IOFactory::createReader(\PHPExcel_IOFactory::identify($filePath));
        $objReader->setReadDataOnly(true);
        $objPHPExcel    = $objReader->load($filePath);

        $this->wsf->renderView('calculator/load_sheet.php', array(
            'file'              => $file,
            'filename'          => $filename,
            'calculator_id'     => $calculatorId,
            'loadedSheetNames'  => $objPHPExcel->getSheetNames(),
           
        ));    
    }
    /*/WPC-PRO*/
   
    /*WPC-PRO*/
    public function loaderMappingAction(){
        $this->wsf->execute('awspricecalculator', true, 'index', 'index');
       
        $calculatorId       = $this->wsf->requestValue('calculator_id');
        $mapping            = $this->wsf->requestValue('mapping');
       
        $fields             = $this->fieldModel->get_field_list('input');
        $outputFields       = $this->fieldModel->get_field_list('output');
       
        $mappingInput       = null;
        $mappingOutput      = null;
        $mappingInfo        = null;
        $calculator         = null;
       
        if(empty($calculatorId)){
            if(empty($_SESSION['woo-price-calculator']['admin']['file']) &&
               empty($_SESSION['woo-price-calculator']['admin']['worksheet'])){
                $file           = $this->wsf->requestValue('file');
                $filename       = $this->wsf->requestValue('filename');
                $worksheet      = $this->wsf->requestValue('worksheet');

                $_SESSION['woo-price-calculator']['admin']['file']          = $file;
                $_SESSION['woo-price-calculator']['admin']['filename']      = $filename;
                $_SESSION['woo-price-calculator']['admin']['worksheet']     = $worksheet;

            }else{
                $file           = $_SESSION['woo-price-calculator']['admin']['file'];
                $filename       = $_SESSION['woo-price-calculator']['admin']['filename'];
                $worksheet      = $_SESSION['woo-price-calculator']['admin']['worksheet'];
            }

        }else{
            $calculator     = $this->calculatorModel->get($calculatorId);
            $mappingInfo    = json_decode($calculator->options, true);

            $file           = $mappingInfo['file'];
            $filename       = $mappingInfo['filename'];
            $worksheet      = $mappingInfo['worksheet'];

        }
       
        if(!empty($file)){

            $filePath       = $this->wsf->getUploadPath('docs/' . $file);
            $objReader      = \PHPExcel_IOFactory::createReader(\PHPExcel_IOFactory::identify($filePath));
            $objReader->setReadDataOnly(true);
            $objPHPExcel    = $objReader->load($filePath);
            $objWorksheet   = $objPHPExcel->setActiveSheetIndex($worksheet);
            $worksheetData  = $objReader->listWorksheetInfo($filePath);
            $totalColumns   = $worksheetData[0]['totalColumns'];
            $totalRows      = $objWorksheet->getHighestRow();
            /*
             * Imposto il valore 10 nella colonna L, riga 22
             */
            //$objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow(11, 22, "10");


        }

        if($this->wsf->isPost() && $mapping == 1){
            $loader_fields = array();

            /*
             * Campi di input
             */
            foreach($fields as $field){
                $cells  = $this->wsf->requestValue('input_field_' . $field->id, array());

                foreach($cells as $cell){
                    if(!empty($cell)){
                        $loader_fields['input'][$cell] = $field->id;
                    }
                }
            }
           
            /*
             * Campi di output
             */
            $loader_fields['output'][$this->wsf->requestValue('output_field_price')] = 'price';
            foreach($outputFields as $outputField){
                $cell  = $this->wsf->requestValue('output_field_' . $outputField->id, array());

                if(!empty($cell)){
                    $loader_fields['output'][$cell] = $outputField->id;
                }
               
            }
               
            $loader_fields['price']             = $this->wsf->requestValue('price');
           
            $dataArray                              = $this->calculatorModel->exchangeArray($calculator);
            $dataArray['options']['filename']       = $filename;
            $dataArray['options']['input']          = $loader_fields['input'];
            $dataArray['options']['output']         = $loader_fields['output'];
            $dataArray['options']['price']          = $loader_fields['price'];
           
            /* Evito che il campo di output sia anche un campo di input */
            unset($dataArray['options']['input'][$dataArray['options']['output']]);
           
            $this->calculatorModel->save($dataArray, $calculatorId);

            $this->wsf->redirect($this->wsf->adminUrl(array(
                'controller' => 'calculator'
            )));
        }

        $columns    = $totalColumns;
        if($columns < $this->MAX_EMPTY_COLUMNS){
            $columns    = $this->MAX_EMPTY_COLUMNS;
        }

        /* Controllo per il ricaricamente, se il foglio precedentemente caricato
         * è più piccolo rispetto a quello che si sta caricato
         */
        if(!empty($calculatorId)){

            /* Evito di creare celle inesistenti per il controllo */
            $cloneObjWorksheet   = clone $objWorksheet;
           
            /* Controllo le celle di input */
            foreach($mappingInfo['input'] as $coordinates => $fieldId){
                $cell           = $cloneObjWorksheet->getCell($coordinates);
                $colIndex       = \PHPExcel_Cell::columnIndexFromString($cell->getColumn());
                $rowIndex       = $cell->getRow();
               
                if($rowIndex > $totalRows){
                    unset($mappingInfo['input'][$coordinates]);
                }
               
                if($colIndex > $totalColumns){
                    unset($mappingInfo['input'][$coordinates]);
                }
            }
           
            /* Controllo le celle di output */
            foreach($mappingInfo['output'] as $coordinates => $fieldId){
                $cell           = $cloneObjWorksheet->getCell($coordinates);
                $colIndex       = \PHPExcel_Cell::columnIndexFromString($cell->getColumn());
                $rowIndex       = $cell->getRow();

                if($rowIndex > $totalRows){
                    unset($mappingInfo['output'][$coordinates]);
                }
               
                if($colIndex > $totalColumns){
                    unset($mappingInfo['output'][$coordinates]);
                }
            }
           
        }
       
        $this->wsf->renderView('calculator/load_mapping.php', array(
            'calculatorId'      => $calculatorId,
           
            'calculator'        => $calculator,
           
            'file'              => $file,
            'filename'          => $filename,
            'worksheet'         => $worksheet,
           
            'fields'            => $fields,
            'outputFields'      => $outputFields,
           
            'objWorksheet'      => $objWorksheet,
            'worksheetData'     => $worksheetData,
           
            'mappingInfo'       => $mappingInfo,
           
            'columns'           => $columns,
           
            'MAX_EMPTY_ROWS'    => $this->MAX_EMPTY_ROWS,
        ));
    }
    /*/WPC-PRO*/
   
    public function deleteAction(){
        $id = $this->wsf->requestValue('id');
       
        $this->calculatorModel->delete($id);
       
        $this->wsf->execute('awspricecalculator', true, 'calculator', 'index');
    }
   
    public function addAction(){
        $this->wsf->execute('awspricecalculator', true, 'index', 'index');
       
        $id             = $this->wsf->requestValue('id', null);
        $calculatorForm = $this->wsf->get('\\WSF\\Form', true, 'awspricecalculator/Form', 'CalculatorForm', array($this->wsf));
        $form           = $this->wsf->requestForm($calculatorForm);
       
        $task           = $this->wsf->requestValue('task');
        $mapping        = $this->wsf->requestValue('mapping');
        $type           = $this->wsf->requestValue('type');
        $loader_fields  = array();
       
        if(empty($type)){
            $type = "simple";
        }
       
        $fields                 = $this->fieldModel->get_field_list('input');
        $outputFields           = $this->fieldModel->get_field_list('output');
       
        $productCategories      = $this->wooCommerceHelper->getProductCategories();
       
        $themes                 = $this->themeHelper->getThemes();
       
        $errors                 = array();
        $warnings               = array();
       
        if($this->wsf->isPost() && $task == 'calculator'){
            if($type == "simple" || ($type == "excel" && $mapping != 1)){
                $errors         = $calculatorForm->check($form, array('id' => $id));
                $warnings       = $this->calculatorHelper->checkProductPrices($form['products']);
               
                if($type == "excel"){
                    $form['options']         = $_SESSION['woo-price-calculator']['admin']['loader_fields'];
                }
               
                /* In questo modo riesco a prendere l'ordine dei campi */
                if(!empty($form['field_orders'])){
                    $form['fields']         = explode(",", $form['field_orders']);
                }
           
                if(!empty($form['output_field_orders'])){
                    $form['output_fields']         = explode(",", $form['output_field_orders']);
                }
               
                if(count($errors) == 0){

                    $id     = $this->calculatorModel->save($form, $id);

                    //checking if the record was created in the database, if not display an error message
                    if($id == 0){

                        $this->wsf->renderView('app/form_message.php', array(
                            'type'          => 'danger',
                            'message'       => $this->wsf->trans('database_problem'),
                        ));


                    }else {

                        $this->wsf->renderView('app/form_message.php', array(
                            'message'   => $this->wsf->trans('aws.calculator.form.success'),
                            'url'       => $this->wsf->adminUrl(array('controller' => 'calculator')),
                        ));
                    }

                }
            }
        }else{          
            if($type == "excel"){
                /*WPC-PRO*/
               
                /*
                 * Campi di input
                 */
                foreach($fields as $field){
                    $cells  = $this->wsf->requestValue('input_field_' . $field->id);
                   
                    if(!empty($cells)){
                        foreach($cells as $cell){
                            if(!empty($cell)){
                                $loader_fields['input'][$cell] = $field->id;
                            }
                        }
                    }
                }
               
                /*
                 * Campi di output
                 */
                $loader_fields['output'][$this->wsf->requestValue('output_field_price')] = 'price';
                foreach($outputFields as $outputField){
                    $cell  = $this->wsf->requestValue('output_field_' . $outputField->id);

                    if(!empty($cell)){
                        $loader_fields['output'][$cell] = $outputField->id;
                    }

                }

                $loader_fields['price']             = $this->wsf->requestValue('price');
                $loader_fields['file']              = $this->wsf->requestValue('file');
                $loader_fields['filename']          = $this->wsf->requestValue('filename');
                $loader_fields['worksheet']         = $this->wsf->requestValue('worksheet');
               
                $_SESSION['woo-price-calculator']['admin']['loader_fields']     = $loader_fields;
               
                /* Preselezione dei campi mappati */
                if(!isset($loader_fields['input'])){
                    $loader_fields['input']     = array();
                }
                             
                foreach($loader_fields['input'] as $coords => $fieldId){
                    $form['fields'][]           = $fieldId;
                }
               
                if(!isset($loader_fields['output'])){
                    $loader_fields['output']     = array();
                }
                             
                foreach($loader_fields['output'] as $coords => $fieldId){
                    if($fieldId != 'price'){ /* Il price è già incluso di default */
                        $form['output_fields'][]           = $fieldId;
                    }
                }
                         
                /*/WPC-PRO*/
            }
       
           
        }

        $this->wsf->renderView('calculator/calculator.php', array(
            'id'                        => $id,
            'title'                     => $this->wsf->trans('Add'),
            'action'                    => 'add',
           
            'ecommerceHelper'           => $this->wooCommerceHelper,
           
            'errors'                    => $errors,
            'warnings'                  => $warnings,
           
            'form'                      => $form,

            'fields'                    => $fields,
           
            'orderedFields'             => $this->calculatorHelper->orderFields($form['fields'], 'input'),
            'outputOrderedFields'       => $this->calculatorHelper->orderFields($form['output_fields'], 'output'),
           
            'products'                  => $this->wooCommerceHelper->getProductsByIds($form['products']),
            'productCategories'         => $productCategories,
           
            'themes'                    => $themes,
           
            'loader_fields'             => $loader_fields,
           
            'type'                      => $type,
        ));

    }
   
    public function editAction(){
        $this->wsf->execute('awspricecalculator', true, 'index', 'index');
       
        $calculatorForm = $this->wsf->get('\\WSF\\Form', true, 'awspricecalculator/Form', 'CalculatorForm', array($this->wsf));
       
        $id                     = $this->wsf->requestValue('id');
        $task                   = $this->wsf->requestValue('task');
        $fields                 = $this->fieldModel->get_field_list('input');
       
        $productCategories      = $this->wooCommerceHelper->getProductCategories();
       
        $themes                 = $this->themeHelper->getThemes();
        $calculator             = $this->calculatorModel->get($id);
        $calculatorType         = $this->wsf->isset_or($calculator->type, "simple");
       
        $calculatorFields       = json_decode($this->wsf->isset_or($calculator->fields, "{}"), true);
        $calculatorOutputFields = json_decode($this->wsf->isset_or($calculator->output_fields, "{}"), true);
        $calculatorOptions      = json_decode($this->wsf->isset_or($calculator->options, "{}"), true);
           
        $form = $this->wsf->requestForm($calculatorForm, array(
            'name'                          => $calculator->name,
            'description'                   => $this->wsf->isset_or($calculator->description, ""),
            'fields'                        => $calculatorFields,
            'output_fields'                 => $calculatorOutputFields,
            'products'                      => json_decode($this->wsf->isset_or($calculator->products, "{}"), true),
            'product_categories'            => json_decode($this->wsf->isset_or($calculator->product_categories, "{}"), true),
            'overwrite_weight'              => $this->wsf->isset_or($calculator->overwrite_weight, null),
            'options'                       => $calculatorOptions,
            'formula'                       => $this->wsf->isset_or($calculator->formula, ""),
            'redirect'                      => $this->wsf->isset_or($calculator->redirect, 0),
            'empty_cart'                    => $this->wsf->isset_or($calculator->empty_cart, 0),
            'type'                          => $calculatorType,
            'theme'                         => $this->wsf->isset_or($calculator->theme, ""),
            'system_created'                => $this->wsf->isset_or($calculator->system_created, 0),
        ));
           
        $errors         = array();
        $warnings       = array();
       
        if($this->wsf->isPost() && $task == 'calculator'){
            $form               = $this->wsf->requestForm($calculatorForm);
            $errors             = $calculatorForm->check($form, array('id' => $id));
           
            $warnings           = $this->calculatorHelper->checkProductPrices($form['products']);

            /* In questo modo riesco a prendere l'ordine dei campi */
            if(!empty($form['field_orders'])){
                $form['fields']     = explode(",", $form['field_orders']);
            }
           
            if(!empty($form['output_field_orders'])){
                $form['output_fields']     = explode(",", $form['output_field_orders']);
            }
           
            if(count($errors) == 0){      

                    /* Non modifico le informazioni delle opzioni */
                    if($calculatorType == 'excel'){
                        $form['options']     = $calculatorOptions;
                    }
       
       
                    $this->calculatorModel->save($form, $id);
                   
                    $calculator                 = $this->calculatorModel->get($id);
                   
                    $form['fields']             = json_decode($calculator->fields, true);
                    $form['output_fields']      = json_decode($calculator->output_fields, true);
                   
                    $this->wsf->renderView('app/form_message.php', array(
                        'message'       => $this->wsf->trans('aws.calculator.form.success'),
                        'url'           => $this->wsf->adminUrl(array('controller' => 'calculator'))
                    ));
            }

        }

        $this->wsf->renderView('calculator/calculator.php', array(
            'id'                        => $id,
            'title'                     => $this->wsf->trans('Edit'),
            'action'                    => 'edit',
            'form'                      => $form,
           
            'ecommerceHelper'           => $this->wooCommerceHelper,
           
            'errors'                    => $errors,
            'warnings'                  => $warnings,

            'fields'                    => $fields,
           
            'orderedFields'             => $this->calculatorHelper->orderFields($form['fields'], 'input'),
            'outputOrderedFields'       => $this->calculatorHelper->orderFields($form['output_fields'], 'output'),
           
            'products'                  => $this->wooCommerceHelper->getProductsByIds($form['products']),
            'productCategories'         => $productCategories,
           
            'themes'                    => $themes,

            'loader_fields'             => $fields,
           
            'type'                      => $calculator->type,

        ));

    }
   
    function uploadSpreadsheetAction(){
        $targetPath     = $this->wsf->getUploadPath('docs');
        $token          = $this->wsf->requestValue('token');
        $calculatorId   = $this->wsf->requestValue('calculator_id');
       
        if (!empty($_FILES)) {
            $tempFile   = $_FILES['file_upload']['tmp_name'];
            $filename   = $_FILES['file_upload']['name'];

            // Validate the file type
            $fileTypes = array('xls','xlsx', 'ods'); // File extensions
            $fileParts = pathinfo($filename);

            $targetFile = rtrim($targetPath,'/') . '/' . $token;

            if (in_array($fileParts['extension'],$fileTypes)) {
                    move_uploaded_file($tempFile, $targetFile);
                    echo $token;
            } else {
                    echo 'Invalid file type.';
            }
        }

        $redirectUrl    = $this->wsf->adminUrl(array(
            'controller'    => 'calculator',
            'action'        => 'loadersheet',
            'file'          => $token,
            'filename'      => urlencode($filename),
            'calculator_id' => $calculatorId,
        ));
       
        header("location: {$redirectUrl}");
        exit(-1);
    }
   
    public function downloadSpreadsheetAction(){
        $simulatorId          = $this->wsf->requestValue('simulator_id');

        $this->calculatorHelper->downloadSpreadsheet($simulatorId);
    }
   
    public function conditionalLogicAction(){
        $this->wsf->execute('awspricecalculator', true, 'index', 'index');
       
        $id                         = $this->wsf->requestValue('id');
        $task                       = $this->wsf->requestValue('task');
        $calculator                 = $this->calculatorModel->get($id);
        $calculatorConditionalLogic = $this->calculatorModel->getConditionalLogic($id);
        $calculatorFieldsIds        = $this->calculatorHelper->get_simulator_fields($id);
        $fields                     = $this->fieldHelper->get_fields_by_ids($calculatorFieldsIds);
       
        $calculatorConditionalLogicForm         = $this->wsf->get('\\WSF\\Form', true, 'awspricecalculator/Form', 'CalculatorConditionalLogicForm', array($this->wsf));
               
        $form = $this->wsf->requestForm($calculatorConditionalLogicForm, array(
            'enabled'                  => $this->wsf->isset_or($calculatorConditionalLogic['enabled'], 0),
            'hide_fields'              => $this->wsf->isset_or($calculatorConditionalLogic['hide_fields'], array()),
            'field_filters_json'       => $this->wsf->isset_or($calculatorConditionalLogic['field_filters_json'], ""),
            'field_filters_sql'        => $this->wsf->isset_or($calculatorConditionalLogic['field_filters_sql'], ""),

        ));
       
        if($this->wsf->isPost() && $task == 'save'){
            $form                       = $this->wsf->requestForm($calculatorConditionalLogicForm);
           
            /* Converto i filtri JSON per il salvataggio, pulendo */
            foreach($form['field_filters_json'] as $fieldKey => $filterValue){
                $form['field_filters_json'][$fieldKey]  = json_decode(stripslashes($filterValue), true);
            }
           
            /* Converto i filtri SQL per il salvataggio, pulendo */
            foreach($form['field_filters_sql'] as $fieldKey => $filterValue){
                $form['field_filters_sql'][$fieldKey]  = stripslashes($filterValue);
            }

            $this->calculatorModel->saveConditionalLogic($form, $id);
           
            $this->wsf->renderView('app/form_message.php', array(
                'message'    => $this->wsf->trans('aws.conditional_logic.form.success'),
                'url'        => $this->wsf->adminUrl(array(
                    'controller' => 'calculator',
                ))
            ));
        }
       
        $filters                = array();
        foreach($fields as $field){
            $filters[$field->id]                = $this->fieldHelper->convertFieldsToFilters($fields, $field->id);
        }
       
        $this->wsf->renderView('calculator/conditional_logic.php', array(
            'id'                        => $id,
            'calculator'                => $calculator,
           
            'form'                      => $form,
           
            'warnings'                  => array(),
            'errors'                    => array(),
           
            'fields'                    => $fields,
           
            'filters'                   => $filters,
           
            'fieldHelper'               => $this->fieldHelper,
        ));
    }
        
    /* Importa un calcolatore */
    /*WPC-PRO*/
    public function importAction(){
        $task                         = $this->wsf->requestValue('task');

        $this->wsf->execute('awspricecalculator', true, 'index', 'index');
        
        if($task == "import_calculator"){
            $tempFile   = $_FILES['file_upload']['tmp_name'];
            $filename   = $_FILES['file_upload']['name'];
            $fileTypes  = array('zip'); // File extensions
            $fileParts  = pathinfo($filename);
            
            if (in_array($fileParts['extension'], $fileTypes)) {

                $checkVersion   = $this->calculatorHelper->checkImportZipVersion($tempFile);

                if($checkVersion == false){
                    $result['errors'][]       = $this->wsf->trans('calculator.import.complete.errors.invalid_file');
                }else{
                    if($checkVersion['comparison'] == '='){
                         $result         = $this->calculatorHelper->import($tempFile);

                         if($result == false){
                            $result['errors'][]   = $this->wsf->trans('calculator.import.complete.errors.open_zip');
                         }else{
                            $result['errors']     = array(); //Nessun errore 
                         }
                         
                     }else if($checkVersion['comparison'] == '>'){
                         $result['errors'][]       = $this->wsf->trans('calculator.import.complete.errors.greater_version', $checkVersion);
                     }else if($checkVersion['comparison'] == '<'){
                         $result['errors'][]       = $this->wsf->trans('calculator.import.complete.errors.smaller_version', $checkVersion);
                     }
                }

            } else {
                $result['errors'][]       = $this->wsf->trans('invalid_file_type', array('fileTypes' => implode(",", $fileTypes)));
            }

            $this->wsf->renderView('calculator/import_complete.php', $result);
            
        }else{
            $this->wsf->renderView('calculator/import.php', array());
        }
    }
    /*/WPC-PRO*/
    
    /* Esporta un calcolatore */
    public function exportAction(){
        $id                         = $this->wsf->requestValue('id');
        $calculator                 = $this->calculatorModel->get($id);
        
        $filename                   = "calculator_{$id}.zip";
        $filePath                   = $this->calculatorHelper->export($calculator, $filename);
        
        
        header("Content-type: application/zip"); 
        header("Content-Disposition: attachment; filename={$filename}");
        header("Content-length: " . filesize($filePath));
        header("Pragma: no-cache"); 
        header("Expires: 0"); 
        readfile($filePath);
        
        
        die();
    }

}