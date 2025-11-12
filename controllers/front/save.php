<?php
if (!defined('_PS_VERSION_')) { exit; }

class AtechRefurbConsentSaveModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        // NO llamar a parent::initContent() para evitar problemas con templates
        header('Content-Type: application/json');
        
        try {
            // Validar contexto básico
            if (!isset($this->context) || !$this->context) {
                $this->jsonError('Context not available');
                return;
            }
            
            if (!isset($this->context->customer)) {
                $this->jsonError('Customer context not available');
                return;
            }
            
        // IMPORTANTE: NO requerimos login para permitir GUEST CHECKOUT
        // El consentimiento se guarda asociado al carrito (cart_id)
        // Funciona tanto para clientes registrados como invitados
        // La validación del cliente se hace al finalizar el pedido (hookActionValidateOrder)
            
            // Validar carrito
            if (!isset($this->context->cart) || !Validate::isLoadedObject($this->context->cart)) {
                $this->jsonError('Invalid cart', ['cart_exists' => isset($this->context->cart)]);
                return;
            }
            
            $accepted = (int)Tools::getValue('accepted');
            $cartId = (int)$this->context->cart->id;
            
            if ($cartId <= 0) {
                $this->jsonError('Invalid cart ID', ['cart_id' => $cartId]);
                return;
            }
            
            // Verificar que el módulo esté disponible
            if (!class_exists('AtechRefurbConsent')) {
                $this->jsonError('Module class not found');
                return;
            }
            
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
            
            if ($ok) {
                $this->jsonSuccess(['cart_id' => $cartId, 'accepted' => $accepted]);
            } else {
                $this->jsonError('Database error saving consent', ['cart_id' => $cartId, 'accepted' => $accepted]);
            }
            
        } catch (Exception $e) {
            PrestaShopLogger::addLog(
                'ARC Consent ERROR: '.$e->getMessage(),
                3,
                null,
                'Cart',
                0,
                true
            );
            $this->jsonError('Exception: '.$e->getMessage());
        }
    }
    
    private function jsonSuccess($data = [])
    {
        die(json_encode(array_merge(['ok' => true], $data)));
    }
    
    private function jsonError($message, $data = [])
    {
        die(json_encode(array_merge(['ok' => false, 'error' => $message], $data)));
    }
}
