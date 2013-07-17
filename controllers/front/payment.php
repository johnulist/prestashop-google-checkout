<?php

if(!defined('_PS_VERSION_'))
	exit;

class GcheckoutPaymentModuleFrontController extends ModuleFrontController
{
	
	public function initContent()
	{
		$this->display_column_left = false;
		include_once(_PS_MODULE_DIR_.'gcheckout/gcheckout.php');
		include_once(_PS_MODULE_DIR_.'gcheckout/library/googlecart.php');
		include_once(_PS_MODULE_DIR_.'gcheckout/library/googleitem.php');
		include_once(_PS_MODULE_DIR_.'gcheckout/library/googleshipping.php');
		parent::initContent();

		$gcheckout = new GCheckout();

		/*if (_PS_VERSION_ >= '1.5' && !Context::getContext()->customer->isLogged(true))
			Tools::redirect('index.php?co:q:q:qntroller=authentication&back=order.php');
		else if (_PS_VERSION_ < '1.5' && !$cookie->isLogged(true))
			Tools::redirect('authentication.php?back=order.php');
		else if (!$gcheckout->context->cart->getOrderTotal(true, Cart::BOTH))
			Tools::displayError('Error: Empty cart');*/
		
		// Prepare payment

		$currency = $gcheckout->getCurrency($this->context->cart->id_currency);

		if ($this->context->cart->id_currency != $currency->id)
		{
			$this->context->cart->id_currency = (int)$currency->id;
			$this->context->cookie->id_currency = (int)$this->context->cart->id_currency;
			$this->context->cart->update();
			//Tools::redirect('modules/'.$this->name.'/payment.php');
			Tools::redirect($link->getModuleLink('gcheckout', 'payment'));
		}

		$googleCart = new GoogleCart(
			Configuration::get('GCHECKOUT_MERCHANT_ID'),
			Configuration::get('GCHECKOUT_MERCHANT_KEY'),
			Configuration::get('GCHECKOUT_MODE'), $currency->iso_code);
		foreach ($this->context->cart->getProducts() AS $product)
				$googleCart->AddItem(new GoogleItem(utf8_decode($product['name'].
				((isset($product['attributes']) AND !empty($product['attributes'])) ?
				' - '.$product['attributes'] : '')), utf8_decode($product['description_short']),
				(int)$product['cart_quantity'], $product['price_wt'],
				strtoupper(Configuration::get('PS_WEIGHT_UNIT')), (float)$product['weight']));

		if ($wrapping = $this->context->cart->getOrderTotal(true, Cart::ONLY_WRAPPING))
			$googleCart->AddItem(new GoogleItem(utf8_decode($this->l('Wrapping')), '', 1, $wrapping));

		if (_PS_VERSION_ < '1.5')
			foreach ($this->context->cart->getDiscounts() AS $voucher)
				$googleCart->AddItem(new GoogleItem(utf8_decode($voucher['name']),
					utf8_decode($voucher['description']), 1, '-'.$voucher['value_real']));
		else
			foreach ($this->context->cart->getCartRules() AS $cart_tule)
				$googleCart->AddItem(new GoogleItem(utf8_decode($cart_tule['code']),
				utf8_decode($cart_tule['name']), 1, '-'.$cart_tule['value_real']));

		if (!Configuration::get('GCHECKOUT_NO_SHIPPING'))
		{
			$carrier = new Carrier((int)($this->context->cart->id_carrier), $this->context->language->id);
			$googleCart->AddShipping(new GoogleFlatRateShipping(utf8_decode($carrier->name),
				$this->context->cart->getOrderShippingCost($this->context->cart->id_carrier)));
		}

		$googleCart->SetEditCartUrl(Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'order.php');
		$googleCart->SetContinueShoppingUrl(Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'order-confirmation.php');
		$googleCart->SetRequestBuyerPhone(false);
		$googleCart->SetMerchantPrivateData($this->context->cart->id.'|'.$this->context->cart->secure_key);

		$total = $this->context->cart->getOrderTotal();
		
		$this->context->smarty->assign(array(
			'googleCheckoutExtraForm' => $googleCart->CheckoutButtonCode(),
			'total' => $total,
			'googleTotal' => $total,
			'GC_Link' => $this->context->link->getPageLink('order', true, NULL)
		));

		$this->setTemplate('confirm.tpl');
	}
}

?>