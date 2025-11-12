
<?php
class AtechRefurbConsentSaveModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();
        header('Content-Type: application/json');
        $ok = AtechRefurbConsent::setConsent((int)$this->context->cart->id, (int)Tools::getValue('accepted'));
        die(json_encode(['ok' => (bool)$ok]));
    }
}
