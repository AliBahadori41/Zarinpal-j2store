<?php
/**
 * @package     Zarinpal payment gateway for j2store.
 * @subpackage  com_j2store
 * @subpackage 	Zarinpal 
 * @copyright   Ali Bahadori => https://bahadori.dev
 * @copyright   Copyright (C) 2024 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

 
defined('_JEXEC') or die('Restricted access'); ?>

<h5>
    زرین‌پال
</h5>
<p>
    <strong>وضعیت : </strong>
    <?php echo $vars->message; ?>
</p>
<p>
    <strong>شناسه تراکنش : </strong>
    <?php echo $vars->ref_id ?? '-';?>
</p>
