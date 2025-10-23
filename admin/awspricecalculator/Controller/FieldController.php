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

class FieldController {
    var $wsf;
    
    var $db;
    
    public function __construct(FrameworkHelper $wsf){
        $this->wsf = $wsf;
        $this->databaseHelper    = $this->wsf->get('\\WSF\\Helper', true, 'awsframework/Helper', 'DatabaseHelper', array($this->wsf));

        $this->calculatorModel      = $this->wsf->get('\\WSF\\Model', true, 'awspricecalculator/Model', 'CalculatorModel', array($this->wsf));
        $this->fieldModel           = $this->wsf->get('\\WSF\\Model', true, 'awspricecalculator/Model', 'FieldModel', array($this->wsf));
        $this->regexModel           = $this->wsf->get('\\WSF\\Model', true, 'awspricecalculator/Model', 'RegexModel', array($this->wsf));
        
        $this->fieldHelper          = $this->wsf->get('\\WSF\\Helper', true, 'awspricecalculator/Helper', 'FieldHelper', array($this->wsf));
        $this->tableHelper          = $this->wsf->get('\\WSF\\Helper', true, 'awspricecalculator/Helper', 'TableHelper', array($this->wsf));
        
        $currentAction              = $this->wsf->getCurrentActionName();

    }
    
    public function indexAction(){
        $this->wsf->execute('awspricecalculator', true, 'index', 'index');

        $this->wsf->renderView('fields/list.php', array(
            'list_header'    => array(
                'label'         => $this->wsf->trans('wpc.field.list.label'),
                'name'          => $this->wsf->trans('wpc.field.list.name'),
                'mode'          => $this->wsf->trans('wpc.field.list.mode'),
                'type'          => $this->wsf->trans('wpc.field.list.type'),
                'description'   => $this->wsf->trans('wpc.field.list.description'),
                'actions'       => $this->wsf->trans('wpc.actions'),
            ),
            'list_rows'      => $this->fieldModel->get_field_list(),
        ));
    }

    public function deleteAction(){
        $id = $this->wsf->requestValue('id');
               
        $error  = $this->fieldHelper->checkFieldUsage($id);
        
        if(!empty($error)){
            $this->wsf->execute('awspricecalculator', true, 'index', 'index');
            $this->wsf->renderView('fields/field_error.php', array(
                'error'     => $error,
            ));
        }else{
            $this->fieldModel->delete($id);
        }
        
        $this->wsf->execute('awspricecalculator', true, 'field', 'index');
    }
        
    public function formAction(){
        $this->wsf->execute('awspricecalculator', true, 'index', 'index');
        
        $fieldForm              = $this->wsf->get('\\WSF\\Form', true, 'awspricecalculator/Form', 'FieldForm', array($this->wsf));
        $errors                 = array();
        $picklistItemsData      = array();
        $radioItemsData         = array();
        $imageListItemsData     = array();
        
        $id                     = $this->wsf->requestValue('id');
        $cloneId                = $this->wsf->requestValue('clone_id');
        $task                   = $this->wsf->requestValue('task');
        $form                   = null;
        
        if(!empty($id)){
            $record                     = $this->fieldModel->get_field_by_id($id);
            $options                    = json_decode($record->options, true);
        }
        
        if(!empty($cloneId)){
            $record                     = $this->fieldModel->get_field_by_id($cloneId);
            $options                    = json_decode($record->options, true);
        }
        
        
        $form = $this->wsf->requestForm($fieldForm, array(
            'label'                             => $this->wsf->isset_or($record->label, ''),
            'short_label'                       => $this->wsf->isset_or($record->short_label, ''),
            'description'                       => $this->wsf->isset_or($record->description, ''),
            'mode'                              => $this->wsf->isset_or($record->mode, 'input'),
            'type'                              => $this->wsf->isset_or($record->type, ''),
            'required'                          => $this->wsf->isset_or($record->required, false),
            'required_error_message'            => $this->wsf->isset_or($record->required_error_message, ''),
            'checkbox_check_value'              => $this->wsf->isset_or($options['checkbox']['check_value'], ''),
            'checkbox_uncheck_value'            => $this->wsf->isset_or($options['checkbox']['uncheck_value'], ''),
            
            'items_list_id'                     => $this->wsf->isset_or($options['items_list_id'], 1),
            'picklist_items'                    => $this->wsf->isset_or($options['picklist_items'], ""),
            
            /* Radio */
            'radio_image_width'                 => $this->wsf->isset_or($options['radio']['radio_image_width'], ""),
            'radio_image_height'                => $this->wsf->isset_or($options['radio']['radio_image_height'], ""),
            'radio_items'                       => $this->wsf->isset_or($options['radio']['radio_items'], ""),
            
            /* Image List */
            'imagelist_field_image_width'       => $this->wsf->isset_or($options['imagelist']['imagelist_field_image_width'], ""),
            'imagelist_field_image_height'      => $this->wsf->isset_or($options['imagelist']['imagelist_field_image_height'], ""),
            'imagelist_popup_image_width'       => $this->wsf->isset_or($options['imagelist']['imagelist_popup_image_width'], ""),
            'imagelist_popup_image_height'      => $this->wsf->isset_or($options['imagelist']['imagelist_popup_image_height'], ""),
            'imagelist_items'                   => $this->wsf->isset_or($options['imagelist']['imagelist_items'], ""),

            'checkbox_default_status'           => $this->wsf->isset_or($options['checkbox']['default_status'], 0),

            'numeric_default_value'             => $this->wsf->isset_or($options['numeric']['default_value'], ""),
            'numeric_max_value'                 => $this->wsf->isset_or($options['numeric']['max_value'], ""),
            'numeric_max_value_error'           => $this->wsf->isset_or($options['numeric']['max_value_error'], ""),
            'numeric_min_value'                 => $this->wsf->isset_or($options['numeric']['min_value'], ""),
            'numeric_min_value_error'           => $this->wsf->isset_or($options['numeric']['min_value_error'], ""),
            'numeric_decimals'                  => $this->wsf->isset_or($options['numeric']['decimals'], ""),
            'numeric_decimal_separator'         => $this->wsf->isset_or($options['numeric']['decimal_separator'], ""),
            'numeric_thousand_separator'        => $this->wsf->isset_or($options['numeric']['thousand_separator'], ""),
            
            'output_numeric_decimals'           => $this->wsf->isset_or($options['numeric']['decimals'], ""),
            'output_numeric_decimal_separator'  => $this->wsf->isset_or($options['numeric']['decimal_separator'], ""),
            'output_numeric_thousand_separator' => $this->wsf->isset_or($options['numeric']['thousand_separator'], ""),
            
            'text_default_value'                => $this->wsf->isset_or($options['text']['default_value'], ""),
            'text_regex'                        => $this->wsf->isset_or($options['text']['regex'], ""),
            'text_regex_error'                  => $this->wsf->isset_or($options['text']['regex_error'], ""),

            'system_created'                    => $this->wsf->isset_or($record->system_created, 0),
        ));
        

        if($this->wsf->isPost() && $task == 'field_form'){
            $form                       = $this->wsf->requestForm($fieldForm);

            $errors                     = array_merge($fieldForm->check($form, array('id' => $id)), $errors);
            
            $picklistItemsData          = json_decode($this->wsf->requestValue('picklist_items'), true);
            $radioItemsData             = json_decode($this->wsf->requestValue('radio_items'), true);
            $imageListItemsData         = json_decode($this->wsf->requestValue('imagelist_items'), true);
            
            if(count($errors) == 0){
                $insertId     = $this->fieldModel->save($form, $id);

                $id           = (empty($insertId))?$id:$insertId;

                //checking if the record was created in the database, if not display an error message
                if($id == 0){

                    $this->wsf->renderView('app/form_message.php', array(
                        'type'              => 'danger',
                        'message'           => $this->wsf->trans('database_problem'),
                    ));


                }else {

                    $this->wsf->renderView('app/form_message.php', array(
                        'message'       => $this->wsf->trans('aws.field.form.success'),
                        'url'           => $this->wsf->adminUrl(array('controller' => 'field'))
                    ));
                }
            }
        }else{
            $picklistItemsData          = $this->fieldHelper->get_field_picklist_items($record);
            $radioItemsData             = $this->fieldHelper->getFieldItems('radio', $record);
            $imageListItemsData         = $this->fieldHelper->getFieldItems('imagelist', $record);
        }
        
        $this->wsf->renderView('fields/field.php', array(
            'title'                             => $this->wsf->trans('Add'),
            'errors'                            => $errors,
            'form'                              => $form,
            
            /*WPC-PRO*/
            'regex_list'                        => $this->regexModel->get_list(),
            /*/WPC-PRO*/
            
            'id'                                => $id,
            
            'picklist_items_data'               => $picklistItemsData,
            'radio_items_data'                  => $radioItemsData,
            'imagelist_items_data'              => $imageListItemsData,
        ));

    }

}
