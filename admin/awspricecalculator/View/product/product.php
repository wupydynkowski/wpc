<?php
/**
 * @package AWS Price Calculator
 * @author Enrico Venezia
 * @copyright (C) Altos Web Solutions Italia
 * @license GNU/GPL v2 http://www.gnu.org/licenses/gpl-2.0.html
**/

/*AWS_PHP_HEADER*/
?>

<div class="wpc-product-form">
    
    <div class="awspc-output-product">
        <?php echo $this->view['outputResults']; ?>
    </div>
    
    <table>
            <?php foreach($this->view['data'] as $key => $data): ?>
                <tr class="awspc-field-row" data-field-id="<?php echo $data['field']->id; ?>" style="<?php echo ($this->view['conditionalLogic'][$data['field']->id] == true)?"":"display:none"; ?>">
                    <td class="awspc-field-widget-line" id="<?php echo $data['inputId']; ?>">
                        <label><?php echo $this->userTrans($data['field']->label); ?></label>
                        <?php echo $data['widget']; ?>
                    </td>
                </tr>
            <?php endforeach; ?>

    </table>
</div>
