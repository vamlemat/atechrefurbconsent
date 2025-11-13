<?php
if (!defined('_PS_VERSION_')) { exit; }

class AtechRefurbConsent extends Module
{
    // Protección contra múltiples ejecuciones de hooks
    private static $validatedCarts = [];
    private static $processedOrders = [];
    
    public function __construct()
    {
        $this->name = 'atechrefurbconsent';
        $this->version = '1.3.4';
        $this->author = 'Atech';
        $this->tab = 'checkout';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();
        $this->displayName = $this->l('Consentimiento para Reacondicionados');
        $this->description = $this->l('Muestra un checkbox obligatorio en checkout cuando el carrito tiene productos de categorías seleccionadas. Compatible con guest checkout (clientes invitados).');
        
        // Verificar que la tabla existe (crear si no existe)
        $this->checkAndCreateTable();
    }
    
    /**
     * Verifica que la tabla de consentimientos existe y la crea si no
     */
    private function checkAndCreateTable()
    {
        $tableName = _DB_PREFIX_.'arc_consent';
        $tableExists = Db::getInstance()->executeS('SHOW TABLES LIKE "'.$tableName.'"');
        
        if (empty($tableExists)) {
            PrestaShopLogger::addLog(
                'ARC: Tabla '.$tableName.' no existe. Creándola...',
                2,
                null,
                'Module',
                0,
                true
            );
            $this->installSql();
        }
    }

    public function install()
    {
        return parent::install()
            && $this->installSql()
            && $this->registerHook('displayCheckoutSummaryTop')
            && $this->registerHook('displayPaymentTop')
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
        return $this->renderCheckbox();
    }

    /** FRONT UI: muestra checkbox en paso de pago (después del login) */
    public function hookDisplayPaymentTop($params)
    {
        return $this->renderCheckbox();
    }

    /** 
     * Renderiza el checkbox de consentimiento si es necesario
     * IMPORTANTE: Funciona tanto para clientes registrados como invitados (guest checkout)
     * El consentimiento se asocia al carrito, NO al cliente
     */
    private function renderCheckbox()
    {
        // Verificar que haya categorías configuradas
        $ids = $this->getSelectedCategoryIds();
        if (empty($ids)) { return ''; }

        // Verificar que haya un carrito válido (funciona con invitados)
        if (!isset($this->context->cart) || !Validate::isLoadedObject($this->context->cart)) {
            return '';
        }

        // Verificar que el carrito tenga productos de las categorías
        $needs_consent = $this->cartHasAnyCategory($this->context->cart, $ids);
        if (!$needs_consent) { return ''; }

        // Verificar si ya se aceptó (busca por cart_id, no por customer_id)
        $accepted = $this->getConsent((int)$this->context->cart->id);
        
        $this->context->smarty->assign([
            'arc_text' => Configuration::get('ARC_CHECK_TEXT'),
            'arc_checked' => (bool)$accepted,
            'arc_customer_logged' => $this->context->customer->isLogged(),
            'arc_is_guest' => $this->context->customer->is_guest,
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
        
        // Protección contra múltiples ejecuciones
        if (in_array($cartId, self::$validatedCarts)) {
            PrestaShopLogger::addLog(
                'ARC Consent validation: Cart='.$cartId.' SKIPPED (already validated)',
                1,
                null,
                'Cart',
                $cartId,
                true
            );
            return; // Ya validado previamente
        }
        
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
        
        // Marcar como validado para evitar múltiples verificaciones
        self::$validatedCarts[] = $cartId;
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
        $id_cart = (int)$id_cart;
        $sql = 'SELECT accepted FROM `'._DB_PREFIX_.'arc_consent` WHERE id_cart='.$id_cart;
        $result = (bool)Db::getInstance()->getValue($sql);
        
        // Log para debugging
        PrestaShopLogger::addLog(
            'ARC getConsent: Cart='.$id_cart.' Result='.($result ? 'TRUE' : 'FALSE').' SQL='.$sql,
            1,
            null,
            'Cart',
            $id_cart,
            true
        );
        
        return $result;
    }

    public static function setConsent($id_cart, $accepted)
    {
        $id_cart = (int)$id_cart;
        $accepted = (int)$accepted;
        $db = Db::getInstance();
        
        // Verificar si ya existe
        $checkSql = 'SELECT id_arc, accepted FROM `'._DB_PREFIX_.'arc_consent` WHERE id_cart='.$id_cart;
        $existing = $db->getRow($checkSql);
        
        PrestaShopLogger::addLog(
            'ARC setConsent START: Cart='.$id_cart.' Accepted='.$accepted.' Existing='.json_encode($existing),
            1,
            null,
            'Cart',
            $id_cart,
            true
        );
        
        if ($existing) {
            // UPDATE
            $sql = 'UPDATE `'._DB_PREFIX_.'arc_consent` 
                    SET accepted='.$accepted.', date_add=NOW() 
                    WHERE id_cart='.$id_cart;
        } else {
            // INSERT
            $sql = 'INSERT INTO `'._DB_PREFIX_.'arc_consent` (id_cart, accepted, date_add)
                    VALUES ('.$id_cart.', '.$accepted.', NOW())';
        }
        
        $result = $db->execute($sql);
        
        // Verificar que se guardó
        $verify = $db->getRow('SELECT * FROM `'._DB_PREFIX_.'arc_consent` WHERE id_cart='.$id_cart);
        
        PrestaShopLogger::addLog(
            'ARC setConsent END: Cart='.$id_cart.' SQLResult='.($result ? 'OK' : 'FAIL').' Verify='.json_encode($verify).' SQL='.$sql,
            $result ? 1 : 3,
            null,
            'Cart',
            $id_cart,
            true
        );
        
        return $result;
    }

/** AFTER ORDER CREATED: add private message with consent text */
public function hookActionObjectOrderAddAfter($params)
{
    try {
        if (empty($params['object']) || !($params['object'] instanceof Order)) {
            PrestaShopLogger::addLog(
                'ARC hookActionObjectOrderAddAfter: Invalid order object',
                2,
                null,
                'Order',
                0,
                true
            );
            return;
        }
        
        /** @var Order $order */
        $order = $params['object'];
        $orderId = (int)$order->id;
        $cartId = (int)$order->id_cart;
        
        PrestaShopLogger::addLog(
            'ARC hookActionObjectOrderAddAfter START: Order='.$orderId.' Cart='.$cartId,
            1,
            null,
            'Order',
            $orderId,
            true
        );
        
        // Protección contra múltiples ejecuciones
        if (in_array($orderId, self::$processedOrders)) {
            PrestaShopLogger::addLog(
                'ARC hookActionObjectOrderAddAfter: Order='.$orderId.' SKIPPED (already processed)',
                1,
                null,
                'Order',
                $orderId,
                true
            );
            return;
        }
        self::$processedOrders[] = $orderId;

        // Only if cart had required categories and consent is present
        $ids = $this->getSelectedCategoryIds();
        if (empty($ids)) {
            PrestaShopLogger::addLog(
                'ARC hookActionObjectOrderAddAfter: No categories configured',
                1,
                null,
                'Order',
                $orderId,
                true
            );
            return;
        }

        $cart = new Cart($cartId);
        if (!Validate::isLoadedObject($cart)) {
            PrestaShopLogger::addLog(
                'ARC hookActionObjectOrderAddAfter: Cart='.$cartId.' not loaded',
                2,
                null,
                'Order',
                $orderId,
                true
            );
            return;
        }
        
        if (!$this->cartHasAnyCategory($cart, $ids)) {
            PrestaShopLogger::addLog(
                'ARC hookActionObjectOrderAddAfter: Cart='.$cartId.' has no required categories',
                1,
                null,
                'Order',
                $orderId,
                true
            );
            return;
        }

        if (!$this->getConsent($cartId)) {
            PrestaShopLogger::addLog(
                'ARC hookActionObjectOrderAddAfter: Cart='.$cartId.' has no consent',
                2,
                null,
                'Order',
                $orderId,
                true
            );
            return;
        }

        // Build message text
        $text = (string)Configuration::get('ARC_CHECK_TEXT');
        if (trim($text) === '') {
            $text = 'Consentimiento aceptado para productos de categorías configuradas.';
        }
        $full = 'Consentimiento Reacondicionados: ' . $text;

        // Create private message on order
        $msg = new Message();
        $msg->id_order = $orderId;
        $msg->message = $full;
        $msg->private = 1;
        $msg->id_employee = 0;
        $msg->id_customer = (int)$order->id_customer;
        
        $result = false;
        if (method_exists($msg, 'add')) {
            $result = $msg->add();
        }
        
        if ($result) {
            PrestaShopLogger::addLog(
                'ARC: ✓ Mensaje privado añadido correctamente al pedido='.$orderId.' Cart='.$cartId.' MsgID='.$msg->id,
                1,
                null,
                'Order',
                $orderId,
                true
            );
        } else {
            PrestaShopLogger::addLog(
                'ARC: ✗ Error al añadir mensaje al pedido='.$orderId.' Cart='.$cartId,
                3,
                null,
                'Order',
                $orderId,
                true
            );
        }

        // IMPORTANTE: NO borramos el consentimiento aquí
        // Causa conflictos con múltiples ejecuciones de hooks
        // Los registros se pueden limpiar manualmente o con un cronjob después de X días
        // La tabla es pequeña y no afecta el rendimiento
        
    } catch (Exception $e) {
        PrestaShopLogger::addLog(
            'ARC hookActionObjectOrderAddAfter EXCEPTION: '.$e->getMessage(),
            4,
            null,
            'Order',
            isset($orderId) ? $orderId : 0,
            true
        );
    }
}

}
