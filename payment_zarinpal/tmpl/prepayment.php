<?php
/**
 * @package     Zarinpal payment gateway for j2store.
 * @subpackage  com_j2store
 * @subpackage 	Zarinpal 
 * @copyright   Ali Bahadori => https://bahadori.dev
 * @copyright   Copyright (C) 2024 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */
defined('_JEXEC') or die('Restricted access');
?>

<p><?php echo 'درگاه زرین پال' ?></p>

<?php if ($vars->errors && count($vars->errors) > 0 ) : ?>
    <div
        class="alert alert-danger"
        role="alert"
    >
        <strong>اخطار : </strong>
        <br>
        <?php echo $vars->errors['message']; ?>
    </div>
    
<?php else: ?>
    <a href="<?php echo $vars->zarinpal; ?>" class="button btn btn-primary">
        تایید و پرداخت
    </a>
<?php endif; ?>
