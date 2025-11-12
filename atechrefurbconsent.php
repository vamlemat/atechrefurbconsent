<?php
if (!defined('_PS_VERSION_')) { exit; }

class AtechRefurbConsent extends Module
{
    public function __construct()
    {
        $this->name = 'atechrefurbconsent';
        $this->version = '1.2.0';
        $this->author = 'Atech';
        $this->tab = 'checkout';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();
        $this->displayName = $this->l('Consentimiento para Reacondicionados');
        $this->description = $this->l('Muestra un checkbox obligatorio en checkout cuando el carrito tiene productos de categorías seleccionadas.');
    }

    public function install()
    {
        return parent::install()
            && $this->installSql()
            && $this->registerHook('displayCheckoutSummaryTop')
            && $this->registerHook('actionFrontControllerSetMedia')
            && $this->registerHook('actionValidateOrder')
                && $this->registerHook('actionObjectOrderAddAfter')
            && Configuration::updateValue('ARC_CATEGORY_IDS', '19')
            && Configuration::updateValue('ARC_CHECK_TEXT', 'ACEPTO QUE LOS PRODUCTOS REACONDICIONADOS NO TIENEN DEVOLUCIÓN NI CAMBIO. LOS CIRCUITOS ESTÁN PROBADOS. SI NO ACEPTA NO LO COMPRE.');
    }

    public function uninstall()
    {
        return $this->uninstallSql()
            && Configuration::deleteByName('ARC_CATEGORY_IDS')
            && Configuration::deleteByName('ARC_CHECK_TEXT')
            && parent::uninstall();
    }

    private function installSql()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'arc_consent` (
            `id_arc` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_cart` INT UNSIGNED NOT NULL,
            `accepted` TINYINT(1) NOT NULL DEFAULT 0,
            `date_add` DATETIME NOT NULL,
            PRIMARY KEY (`id_arc`),
            UNIQUE KEY `uniq_cart` (`id_cart`)
        ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;';
        return Db::getInstance()->execute($sql);
    }

    private function uninstallSql()
    {
        $sql = 'DROP TABLE IF EXISTS `'._DB_PREFIX_.'arc_consent`';
        return Db::getInstance()->execute($sql);
    }

    /*** ====== CONFIGURACIÓN (categorías múltiples + mensaje) ====== ***/
    public function getContent()
    {
        $output = '';
        if (Tools::isSubmit('submitArc')) {
            $ids = Tools::getValue('categoryBox');
            if (!is_array($ids)) {
                $csv = trim((string)Tools::getValue('ARC_CATEGORY_IDS', ''));
                $ids = $csv !== '' ? array_map('intval', array_filter(array_map('trim', explode(',', $csv)))) : [];
            }
            $ids = array_unique(array_map('intval', (array)$ids));
            Configuration::updateValue('ARC_CATEGORY_IDS', implode(',', $ids));

            Configuration::updateValue('ARC_CHECK_TEXT', Tools::getValue('ARC_CHECK_TEXT'));
            $output .= $this->displayConfirmation($this->l('Guardado correctamente.'));
        }

        $selected_ids = $this->getSelectedCategoryIds();
        $tree = new HelperTreeCategories('arc_cats');
        $tree->setUseCheckBox(true)
            ->setUseSearch(true)
            ->setRootCategory((int)Configuration::get('PS_ROOT_CATEGORY'))
            ->setSelectedCategories($selected_ids)
            ->setInputName('categoryBox')
            ->setFullTree(true)
            ->setTitle($this->l('Seleccione categorías que requieren el consentimiento'));

        $output .= '
        <form method="post">
          <div class="panel">
            <div class="panel-heading">'.$this->l('Ajustes de consentimiento').'</div>

            <div class="form-group">
              <label class="control-label col-lg-3">'.$this->l('Categorías afectadas').'</label>
              <div class="col-lg-9">
                '.$tree->render().'
                <p class="help-block">'.$this->l('Si el carrito contiene productos en cualquiera de estas categorías, se mostrará el checkbox obligatorio.').'</p>
              </div>
            </div>

            <div class="form-group clearfix">
              <label class="control-label col-lg-3">'.$this->l('Texto del checkbox').'</label>
              <div class="col-lg-9">
                <textarea name="ARC_CHECK_TEXT" class="form-control" rows="3">'.Tools::safeOutput(Configuration::get('ARC_CHECK_TEXT')).'</textarea>
                <p class="help-block">'.$this->l('Puedes personalizar completamente el mensaje que verá el cliente.').'</p>
              </div>
            </div>

            <div class="panel-footer">
              <button class="btn btn-primary" name="submitArc"><i class="icon-save"></i> '.$this->l('Guardar').'</button>
            </div>
          </div>
        </form>';

        return $output;
    }

    /** FRONT: añade JS solo en checkout */
    public function hookActionFrontControllerSetMedia($params)
    {
        if ($this->isCheckout()) {
            $this->context->controller->registerJavascript(
                'module-arc',
                'modules/'.$this->name.'/views/js/checkbox.js',
                ['position' => 'bottom', 'priority' => 150]
            );
            
            // Log para verificar que se carga el JS
            PrestaShopLogger::addLog(
                'ARC: JS cargado en controlador: '.Dispatcher::getInstance()->getController(),
                1,
                null,
                'Controller',
                0,
                true
            );
        }
    }

    /** FRONT UI: muestra checkbox si procede */
    public function hookDisplayCheckoutSummaryTop($params)
    {
        $ids = $this->getSelectedCategoryIds();
        if (empty($ids)) { return ''; }

        $needs_consent = $this->cartHasAnyCategory($this->context->cart, $ids);
        if (!$needs_consent) { return ''; }

        $accepted = $this->getConsent((int)$this->context->cart->id);
        $this->context->smarty->assign([
            'arc_text' => Configuration::get('ARC_CHECK_TEXT'),
            'arc_checked' => (bool)$accepted,
        ]);
        return $this->fetch('module:'.$this->name.'/views/templates/hook/checkout_checkbox.tpl');
    }

    /** BACK: verificación dura antes de crear pedido */
    public function hookActionValidateOrder($params)
    {
        $ids = $this->getSelectedCategoryIds();
        if (empty($ids)) { return; }

        $cart = $params['cart'];
        if (!$this->cartHasAnyCategory($cart, $ids)) { return; }

        $cartId = (int)$cart->id;
        $hasConsent = $this->getConsent($cartId);
        
        // Log para debugging
        PrestaShopLogger::addLog(
            'ARC Consent validation: Cart='.$cartId.' HasConsent='.($hasConsent ? 'YES' : 'NO'),
            $hasConsent ? 1 : 3,
            null,
            'Cart',
            $cartId,
            true
        );
        
        if (!$hasConsent) {
            throw new PrestaShopException($this->l('Debes aceptar la condición para productos seleccionados antes de finalizar la compra.'));
        }
    }

    /*** ====== HELPERS ====== ***/
    private function isCheckout()
    {
        $controller = Dispatcher::getInstance()->getController();
        $checkoutControllers = [
            'order',
            'checkout',
            'orderopc',
            'orderconfirmation',
            'cart'
        ];
        return in_array($controller, $checkoutControllers);
    }

    private function getSelectedCategoryIds()
    {
        $csv = trim((string)Configuration::get('ARC_CATEGORY_IDS', ''));
        if ($csv === '') { return []; }
        $ids = array_filter(array_map('intval', explode(',', $csv)));
        return array_values(array_unique($ids));
    }

    private function cartHasAnyCategory(Cart $cart, array $ids_wanted)
    {
        if (empty($ids_wanted)) return false;
        $ids_wanted = array_map('intval', $ids_wanted);

        foreach ($cart->getProducts() as $p) {
            $ids = Product::getProductCategories((int)$p['id_product']);
            if (!is_array($ids)) { continue; }
            foreach ($ids as $catId) {
                if (in_array((int)$catId, $ids_wanted, true)) {
                    return true;
                }
            }
        }
        return false;
    }

    private function getConsent($id_cart)
    {
        $sql = 'SELECT accepted FROM `'._DB_PREFIX_.'arc_consent` WHERE id_cart='.(int)$id_cart;
        return (bool)Db::getInstance()->getValue($sql);
    }

    public static function setConsent($id_cart, $accepted)
    {
        $db = Db::getInstance();
        $exists = (bool)$db->getValue('SELECT id_arc FROM `'._DB_PREFIX_.'arc_consent` WHERE id_cart='.(int)$id_cart);
        if ($exists) {
            $sql = 'UPDATE `'._DB_PREFIX_.'arc_consent` SET accepted='.(int)$accepted.' WHERE id_cart='.(int)$id_cart;
        } else {
            $sql = 'INSERT INTO `'._DB_PREFIX_.'arc_consent` (id_cart, accepted, date_add)
                    VALUES ('.(int)$id_cart.', '.(int)$accepted.", '".pSQL(date('Y-m-d H:i:s'))."')";
        }
        return $db->execute($sql);
    }

/** AFTER ORDER CREATED: add private message with consent text */
public function hookActionObjectOrderAddAfter($params)
{
    if (empty($params['object']) || !($params['object'] instanceof Order)) {
        return;
    }
    /** @var Order $order */
    $order = $params['object'];

    // Only if cart had required categories and consent is present
    $ids = $this->getSelectedCategoryIds();
    if (empty($ids)) { return; }

    $cart = new Cart((int)$order->id_cart);
    if (!Validate::isLoadedObject($cart)) { return; }
    if (!$this->cartHasAnyCategory($cart, $ids)) { return; }

    if (!$this->getConsent((int)$order->id_cart)) { return; }

    // Build message text
    $text = (string)Configuration::get('ARC_CHECK_TEXT');
    if (trim($text) === '') {
        $text = 'Consentimiento aceptado para productos de categorías configuradas.';
    }
    $full = 'Consentimiento Reacondicionados: ' . $text;

    // Create private message on order
    $msg = new Message();
    $msg->id_order = (int)$order->id;
    $msg->message = $full;
    $msg->private = 1;
    $msg->id_employee = 0;
    $msg->id_customer = (int)$order->id_customer;
    if (method_exists($msg, 'add')) {
        $msg->add();
    }

    // Optional: cleanup saved consent for the cart
    Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'arc_consent` WHERE id_cart='.(int)$order->id_cart);
}

}
