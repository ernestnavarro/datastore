<?php
include_once ("datastore2.php");
$isdk = new LazyLoadingProxy('iSDK', array('foxxdating'));
class DataStore {
	function UserHasBoughtProduct($Session, $contact_id, $product_id) {
		global $isdk;
		$return = false;
		$bought = array();
		$invoices = $isdk->dsQuery("Invoice", 1000, 0,
			array(
				'ContactId' => $contact_id,
				'PayStatus' => 1,
				'RefundStatus' => 0,
			),
			array( "Id" )
		);
		//print_r($invoices);
		foreach($invoices as $invoice) {
			$invoice_id = $invoice['Id'];
			$invoice_items = $isdk->dsQuery("InvoiceItem", 1000, 0,
				array(
					'InvoiceId' => $invoice_id,
				),
				array( "OrderItemId", 'DateCreated' )
			);
			foreach($invoice_items as $invoice_item) {
				$products = $isdk->dsQuery("OrderItem", 1, 0,
					array(
						'Id' => $invoice_item['OrderItemId'],
					),
					array('ProductId')
				);
				if(!empty($products[0])) {
					if($products[0]['ProductId'] == $product_id) {
						$return = true;
					}
					$bought[$products[0]['ProductId']] = array(
						'InvoiceId'   => $invoice_id,
						'OrderItemId' => $invoice_item['OrderItemId'],
						'BoughtAt'    => $invoice_item['DateCreated'],
					);
					//echo "user did buy "; print_r($products);
				}
			}
		}
		//print_r($bought);
		$Session("Bought", $bought);
		return $return;
	}
	
	function UpdateContact($Session, $fields) {
		global $isdk;
		$contact = array(
			'FirstName' => $fields['fname'],
			'LastName' => $fields['lname'],
			'Email' => $fields['email'],
			'Phone1' => $fields['phone'],
			'StreetAddress1' => $fields['street1'],
			'StreetAddress2' => $fields['street2'],
			'City' => $fields['city'],
			'State' => $fields['state'],
			'PostalCode' => $fields['zip'],
			'Country' => $fields['country'],
		);
		$find = $isdk->dsQuery('Contact', 1, 0, array('Email' => $contact['Email']), array('Id'));
		if(count($find) > 0) {
			$Session('ContactId', $isdk->updateCon($find[0]['Id'], $contact));
		} else {
			$Session('ContactId', (int)$isdk->addCon($contact));
		}

		$card = array(
			'ContactId' => $Session('ContactId'),
			'CardNumber' => $fields['ccn'],
			'ExpirationMonth' => sprintf('%02d', $fields['expMonth']),
			'ExpirationYear' => $fields['expYear'],
			'CVV2' => $fields['cvv'],
		);

		$card['CardNumber'] = str_replace(' ', '', $card['CardNumber']); 
		$card['CardNumber'] = str_replace('-', '', $card['CardNumber']); 
		if(!is_valid_luhn($card['CardNumber']) || $isdk->validateCard($card)['Valid'] != 'true') {
			throw new FormException("CCN not valid");
		}
		$yr = $card['ExpirationYear'];
		if(strlen($yr) == 2) {
			$yr = "20" + $yr;
		}

		if
		(
			$yr < date('Y') || (
				$yr == date('Y') &&
				$card['ExpirationMonth'] < date('m')
			)
		) {
			throw new FormException("The expiration date cannot be in the past.");
		}

		$find = $isdk->dsQuery('CreditCard', 1, 0,
			array('ContactId' => $Session('ContactId'), 'Last4' => substr($card['CardNumber'], -4), 'ExpirationMonth' => $card['ExpirationMonth'], 'ExpirationYear' => $card['ExpirationYear']),
			array('Id','ExpirationMonth', 'ExpirationYear')
		);
		//echo "FIND: ";print_r($find);
		if(count($find) > 0) {
			$Session('CreditCardId', $find[0]['Id']);
		} else {
			$card['NameOnCard'] = $card['BillName'] = $contact['FirstName'].' '.$contact['LastName'];
			$card['PhoneNumber'] = $contact['Phone1'];
			$card['Email'] = $contact['Email'];
			$card['BillAddress1'] = $contact['StreetAddress1'];
			$card['BillAddress2'] = $contact['StreetAddress2'];
			$card['BillCity'] = $contact['City'];
			$card['BillState'] = $contact['State'];
			$card['BillZip'] = $contact['PostalCode'];
			$card['BillCountry'] = $contact['Country'];
			$c = $isdk->dsAdd('CreditCard', $card);
			//echo "CC Add: ";print_r($c);
			$Session('CreditCardId', (int)$c);
		}
		
	}

	function getMerchant($Session, $fields) {
		$merchants = array(
			'test' => array(
				'id' => 4,
				'pay_plan' => 2,
			),
			'signature' => array(
				'id' => 6,
				'pay_plan' => 4,
			),
		);
		$m = function() {
			//echo "using legit merchant";
			return 'signature';
		};
		if($fields['ccn'] == "4111111111111111" ) {
			//echo "using test merchant";
			$stored = 'test';
		} else {
			$stored = $Session('MerchantId');
			//echo "using stored $stored";
		}
		if(!empty($stored) && !empty($merchants[$stored])) {
		} else {
			$stored = $m();
		}
		$Session('MerchantId', $stored);
		return $merchants[$stored];
	}

	function GetInvoice($Session, $product_id) {
		return $Session("InvoiceId_$product_id");
	}

	function BuyProduct($Session, $product_id, $subscription_id=null, $fields=array()) {
		global $isdk;
		$invoice_key = "InvoiceId_$product_id";
		$invoice_id = $Session($invoice_key);
		$merchant = $this->getMerchant($Session, $fields);
		$result = null;
		if(empty($invoice_id)) {
			$result = $isdk->placeOrder(
				$Session('ContactId'),
				$Session('CreditCardId'),
				$merchant['pay_plan'],
				array($product_id),
				!is_null($subscription_id) ?  array($subscription_id) : array(),
				false,
				array()
			);
			//if(!$new_invoice || empty($invoice_id)) {
				$Session($invoice_key, $result['InvoiceId']);
			//}
			$Session("Response", $result);
		} else {
			//echo "charging invoice ".$invoice_id;
			$result = $isdk->chargeInvoice($invoice_id, 'API Charge', $Session('CreditCardId'), $merchant['id'], false);
		}
		
		if(!empty($result['Successful']) && $result['Successful'] === 'false' || $result['Code'] == 'Error') {
			throw new FormException("Order Failed: ".$result['Message']);
		}

		//if(isset($result['InvoiceId'])) {
		//	$Session("InvoiceId", $result['InvoiceId']);
		//}
		if(isset($result['OrderId'])) {
			$Session('OrderId', $result['OrderId']);
		}
		$Session('MerchantId', $stored);

		sleep(1);
		
		$valid_order = $this->validateOrder($Session, $product_id);

		if($valid_order == false) {
			throw new FormException("Order failed: Your order could not be found. This is probably indicative of an internal error.");
		}
	}

	function validateOrder($Session, $product_id) {
		global $isdk;

		// Is it in Infusionsoft? If not, redirect to cart.
		$query = array('Id' => $this->GetInvoice($Session, $product_id), 'ContactId' => $Session('ContactId'));
		$return = array('Id', 'PayStatus');
		$invoice = $isdk->dsQuery('Invoice', 1, 0, $query, $return);
		//echo "QUERY"; print_r($query);
		//echo "INVOICE"; print_r($invoice);
		if(count($invoice) == 0) return false;
		
		$invoice_id = (int)$invoice[0]['Id'];
		
		return $invoice[0]['PayStatus'] == 1 ? 'paid' : 'unpaid';
	
	}

}
