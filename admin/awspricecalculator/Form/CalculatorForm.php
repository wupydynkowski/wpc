<?php
/**
 * @package AWS Price Calculator
 * @author Enrico Venezia
 * @copyright (C) Altos Web Solutions Italia
 * @license GNU/GPL v2 http://www.gnu.org/licenses/gpl-2.0.html
**/

namespace WSF\Form;

/*AWS_PHP_HEADER*/

use WSF\Helper\FrameworkHelper;

class CalculatorForm {
    
    private $wsf;
    
    private $form;
    
    private $calculatorModel;
    
    public function __construct(FrameworkHelper $wsf) {
        $this->wsf = $wsf;
        
        /* MODELS */
        $this->calculatorModel      = $this->wsf->get('\\WSF\\Model', true, 'awspricecalculator/Model', 'CalculatorModel', array($this->wsf));
        
        /* HELPERS */
        $this->calculatorHelper     = $this->wsf->get('\\WSF\\Helper', true, 'awspricecalculator/Helper', 'CalculatorHelper', array($this->wsf));
        $this->ecommerceHelper      = $this->wsf->get('\\WSF\\Helper', true, 'awsframework/Helper', 'EcommerceHelper', array($this->wsf));
        
        $this->form[] = array(
            'name' => 'name'
        );
        
        $this->form[] = array(
            'name' => 'description'
        );
        
        $this->form[] = array(
            'name'      => 'fields',
            'default'   => array()
        );
        
        $this->form[] = array(
            'name'      => 'output_fields',
            'default'   => array()
        );
        
        $this->form[] = array(
            'name'      => 'products',
            'default'   => array()
        );
        
        $this->form[] = array(
            'name'      => 'product_categories',
            'default'   => array()
        );
        
        $this->form[] = array(
            'name'      => 'overwrite_weight',
            'default'   => null
        );
        
        $this->form[] = array(
            'name'      => 'options',
            'default'   => array()
        );
        
        $this->form[] = array(
            'name'      => 'field_orders',
            'default'   => array(),
        );
        
        $this->form[] = array(
            'name'      => 'output_field_orders',
            'default'   => array(),
        );
        
        $this->form[] = array(
            'name' => 'formula'
        );
        
        $this->form[] = array(
            'name' => 'redirect'
        );
        
        $this->form[] = array(
            'name' => 'empty_cart'
        );
        
        $this->form[] = array(
            'name' => 'type'
        );
                
        $this->form[] = array(
            'name' => 'theme'
        );
        
        $this->form[] = array(
            'name' => 'system_created'
        );
        
    }
    
    public function check($record, $params = array()){
        
        $errors = array();
        
        /* Controllo che il nome non sia vuoto */
        if(empty($record['name'])){
            $errors[] = "- Name must not be empty.";
        }

        /* Controllo della formula per i calcolatori semplici */
        if($record['type'] == "simple"){
            $fieldIds                 = $record['fields'];
            $fieldsDefaultValue       = $this->calculatorHelper->getFieldsDefaultValueByFieldIds($fieldIds);
            
            /* Prodotto fasullo */
            $product                  = $this->ecommerceHelper->getProductArray(0, 'Checking formula', 100);
            $calculatorArray          = array_merge($record, array(
                'id'                    => 0,
                'conditional_logic'     => null,
            ));
                    
            $calculator                     = $this->calculatorModel->exchangeObject($calculatorArray);

            /* Se non funziona, ritorna un'eccezione */
            if(is_a($this->calculatorHelper->calculate($calculator, $product, $fieldsDefaultValue), 'Exception')){
                $errors[] = $this->wsf->trans('calculator.formula_problem');
            }
        }
        
        $errors     = array_merge($errors, $this->calculatorHelper->checkCalculatorDuplicate($record, $params['id']));
    
        return $errors;
    }
    
    public function getForm(){
        return $this->form;
    }
    
    public function setForm($form){
        $this->form = $form;
    }
}

