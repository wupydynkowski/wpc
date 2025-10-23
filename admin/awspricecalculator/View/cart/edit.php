<?php
/**
 * @package AWS Price Calculator
 * @author Enrico Venezia
 * @copyright (C) Altos Web Solutions Italia
 * @license GNU/GPL v2 http://www.gnu.org/licenses/gpl-2.0.html
**/

/*AWS_PHP_HEADER*/
?>

<span class="wpc-cart-container">
    <?php if($this->getLicense() == 1): ?>
    <!--WPC-PRO-->
        <span class="wpc-edit-icon <?php echo $this->view['cartEditButtonClass']; ?>" data-remodal-target="wpc_cart_item_<?php echo $this->view['cartItemKey']; ?>">
            <span class="cart-text">
                Edytuj
            </span>


        </span>

        <?php echo $this->view['price']; ?>
     <!--/WPC-PRO-->
    <?php else: ?>
        <?php echo $this->view['price']; ?>
    <?php endif; ?>
</span>

<!--WPC-PRO-->
<div class="remodal wpc-cart-form" data-remodal-id="wpc_cart_item_<?php echo $this->view['cartItemKey']; ?>" data-cart-item-key="<?php echo $this->view['cartItemKey']; ?>">
    <form>
        <div class="main-container">
            <div class="page-content">
                <div class="woocommerce">
                    <div class="wpc-modal-title">
                        <h3>Edytuj <?php echo $this->view['product']['name'] ?></h3>
                    </div>

                    <div class="wpc-modal-fields">
                        <?php echo $this->view['modal']; ?>
                    </div>

                    <div class="wpc-cart-item-price">
                        <b>Cena:</b> <span class="price">?</span>
                    </div>

                    <div class="wpc-cart-item-buttons">
                        <button type="button" class="button wpc-cart-edit">Zapisz</button>
                        <button data-remodal-action="cancel" class="button">Anuluj</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
<!--/WPC-PRO-->
