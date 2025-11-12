<?php
class AtechRefurbConsentSaveModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();
        header('Content-Type: application/json');
        
        // Validar que el cliente esté logueado y tenga un carrito válido
        if (!$this->context->customer->isLogged()) {
            die(json_encode(['ok' => false, 'error' => 'Customer not logged']));
        }
        
        if (!Validate::isLoadedObject($this->context->cart)) {
            die(json_encode(['ok' => false, 'error' => 'Invalid cart']));
        }
        
        $accepted = (int)Tools::getValue('accepted');
        $cartId = (int)$this->context->cart->id;
        
        // Guardar consentimiento
        $ok = AtechRefurbConsent::setConsent($cartId, $accepted);
        
        // Log para debugging
        PrestaShopLogger::addLog(
            'ARC Consent saved: Cart='.$cartId.' Accepted='.$accepted.' Result='.($ok ? 'OK' : 'FAIL'),
            $ok ? 1 : 3,
            null,
            'Cart',
            $cartId,
            true
        );
        
        die(json_encode(['ok' => (bool)$ok, 'cart_id' => $cartId, 'accepted' => $accepted]));
    }
}
