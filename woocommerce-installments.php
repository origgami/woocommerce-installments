<?php
/**
 * Plugin Name: WooCommerce Installments
 * Plugin URI: https://github.com/AndersonFranco/woocommerce-installments
 * Description: This plugin appends installments into the product price.
 * Author: Anderson Franco
 * Author URI: http://www.francotecnologia.com/
 * Version: 1.4.0
 * License: GPLv2 or later
 */

class FrancoTecnologiaWooCommerceInstallments {

  // ONLY PRODUCTS WITH PRICE GREATER THAN OR EQUAL TO:
  protected static $priceGreaterThanOrEqualTo = 0;

  // MINIMUM MONTHLY PAYMENT - MUST BE GREATER THAN ZERO:
  protected static $minimumMonthlyPayment = 5;

  // NUMBER OF THE COLUMNS OF THE TABLE:
  protected static $numberOfTableColumns = 2;

  // ADD TO CART - BUTTON POSITION: TOP = true, BOTTOM = false
  protected static $addToCartButtonPosition = false;

  // SHOW COLUMN TOTAL
  protected static $showColumnTotal = false;

  // TABLE HEADER MESSAGE
  protected static $tableHeaderMessage = "";

  // TABLE FOOTER MESSAGE
  protected static $tableFooterMessage = "";

  // USE COEFFICIENT TABLE / INTEREST RATES:
  protected static $useCoefficientTable = false;

  // COEFFICIENT TABLE - INTEREST RATES:
  // http://andersonfranco.github.io/capital-recovery-factor/
  protected static $coefficientTable = array(
    1, 0.52255, 0.35347,
    0.26898, 0.21830, 0.18453,
    0.16044, 0.14240, 0.12838,
    0.11717, 0.10802, 0.10040
  );

  // USE DICTIONARY LANGUAGE (DEFAULT: ENGLISH)
  protected static $useDictLanguage = true;

  //HOW PARCELS ARE DISPLAYED (One below the other or beside)
  protected static $parcelsLayout=2; // 1 | 2

  // IF $useDictLanguage == false, USE THIS WORDS:
  protected static $language = array(
    'or'              => 'ou',
    'Installments'    => 'Parcelas',
    'Amount'          => 'Valor',
    'Total'          =>  'Total',
    'InStock'         => 'Em estoque',
    'OutOfStock'      => 'Indisponível',
    'cartPageMessage' => 'Pague em at&eacute; %d vezes'
  );

  public static function init() {
    if (static::$useDictLanguage) {
      // Default English words
      static::$language = array(
        'or'              => __('or'),
        'Installments'    => __('Installments'),
        'Amount'          => __('Amount'),
        'Total'           => __('Total'),
        'InStock'         => __('InStock'),
        'OutOfStock'      => __('OutOfStock'),
        'cartPageMessage' => __('NO interest for %d months')
      );
    }
    add_action('plugins_loaded', array(get_called_class(), 'actions'));
  }

  public static function actions() {
    // Product Page
    remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_price', 10);
    add_action('woocommerce_single_product_summary', array(get_called_class(), 'actionSingleProduct'), ((static::$addToCartButtonPosition)?30:10));
    // Catalog
    add_action('woocommerce_after_shop_loop_item_title', array(get_called_class(), 'actionLoopItem'), 20);
    // Cart
    if (static::$language['cartPageMessage'] != '') {
      add_action('woocommerce_cart_totals_after_order_total', array(get_called_class(), 'actionCart'), 20);
    }
    // CSS
    add_action('wp_enqueue_scripts', array(get_called_class(), 'css'), 98);
    // Javascript
    add_action('wp_enqueue_scripts', array(get_called_class(), 'js'), 99);
  }

  protected static function calculateInstallment($price = 0.00, $installment = 0) {
    $price        = (float) $price;
    $installment  = (int) $installment;
    $result       = new stdClass();

    if ($installment < 1 || $installment > 12) {
      $result->price = 0;
      $result->total = 0;
    } else if (static::$useCoefficientTable) {
      $result->price = sprintf("%0.2f", $price * static::$coefficientTable[$installment - 1]);
      $result->total = sprintf("%0.2f", ($price * static::$coefficientTable[$installment - 1]) * $installment);
    } else {
      $result->price = sprintf("%0.2f", ($price / $installment));
      $result->total = sprintf("%0.2f", ($price / $installment) * $installment);
    }

    return $result;
  }

  protected static function getPrice($price = null) {
    if ($price === null) {
      $product = get_product();
      if ($product->get_price()) {
        $price = $product->get_price();
      }
    }
    return $price;
  }

  protected static function getInstallments($price = 0.00) {
    $installments = round($price / static::$minimumMonthlyPayment);
    if ($installments > 12) {
      $installments = 12;
    } else if ($installments < 1) {
      $installments = 1;
    }
    return $installments;
  }

  protected static function getParceledValue($price = null) {
    $price = static::getPrice($price);
    if ($price > 0) {
      $installments = static::getInstallments($price);
      $calc         = static::calculateInstallment($price, $installments);
      return $installments . 'x ' . wc_price($calc->price);
    } else {
      return '';
    }
  }

  protected static function createParcelsLayout($table,$price){
  	$installments = static::getInstallments($price);
  	switch(self::$parcelsLayout){
  		case 1:
	  		$tdCounter = 0;
	  		foreach (range(1, $installments) as $parcel) {
	  			$calc = static::calculateInstallment($price, $parcel);
	  			$tdCounter = 1 + $tdCounter % static::$numberOfTableColumns;
	  			if ($tdCounter == 1) {
	  				$table .= '<tr>';
	  			}
	  			$table .= '<th>' . $parcel . '</th><td>' . wc_price($calc->price) . '</td>' . (static::$showColumnTotal ? '<td>' . wc_price($calc->total) . '</td>' : '');
	  			if ($tdCounter == static::$numberOfTableColumns) {
	  				$table .= '</tr>';
	  			}
	  		}
  		break;
  		case 2:
	  		$layout2Offset = ceil($installments/static::$numberOfTableColumns);      
	  		$layout2Counter=0;
	  		for($j=0;$j<$installments;$j++){
	  			for($i=$j;$i<$installments;$i+=$layout2Offset){      
	  				$parcel = ($i+1);          
	  				$calc = static::calculateInstallment($price, $parcel);
	  				$tdCounter = 1 + $tdCounter % static::$numberOfTableColumns;
	  				if ($tdCounter == 1) {
	  					$table .= '<tr>';
	  				}
	  				$table .= '<th>' . $parcel . '</th><td>' . wc_price($calc->price) . '</td>' . (static::$showColumnTotal ? '<td>' . wc_price($calc->total) . '</td>' : '');
	  				if ($tdCounter == static::$numberOfTableColumns) {
	  					$table .= '</tr>';
	  				}
	  				$layout2Counter++;
	  				if($layout2Counter==$installments){
	  					break 2;
	  				}
	  			}
	  		}
  		break;
  	}

  	return $table;
  }

  protected static function getParceledTable($price = null, $variationId = null, $variationDisplay = null) {
    $price = static::getPrice($price);
    if ($price > 0) {
      $installments = static::getInstallments($price);
      $table = '<table class="francotecnologia_wc_parcpagseg_table ';
      $table .= 'francotecnologia_wc_parcpagseg_table_with_variation_id_' . ($variationId > 0 ? $variationId : '0') . '" ';
      $table .= ($variationDisplay === false ? 'style="display:none"' : '');
      $table .= '><thead>';

      $tableColspan = (2 + (static::$showColumnTotal?1:0)) * static::$numberOfTableColumns;

      if (static::$tableHeaderMessage != '') {
        $table .= '<tr><th class="francotecnologia_wc_parcpagseg_table_header_message_tr_th" colspan="'
                . $tableColspan . '">' . static::$tableHeaderMessage . '</th></tr>';
      }

      $table .= '<tr class="francotecnologia_wc_parcpagseg_table_header_tr">';
      $table .= str_repeat('<th>' . static::$language['Installments'] . '</th><th>' . static::$language['Amount'] . '</th>'
                           . (static::$showColumnTotal ? '<th>' . static::$language['Total'] . '</th>':''), static::$numberOfTableColumns);
      $table .= '</tr>';

      $table .= '</thead><tbody>';

      $table = self::createParcelsLayout($table,$price);

      if (substr( $table, -5 ) != '</tr>') {
        $table .= '</tr>';
      }

      $table .= '</tbody>';
      if (static::$tableFooterMessage != '') {
        $table .= '<tfoot><tr><th class="francotecnologia_wc_parcpagseg_table_footer_message_tr_th" colspan="'
                . $tableColspan . '">' . static::$tableFooterMessage . '</th></tr></tfoot>';
      }
      $table .= '</table>';
      return $table;
    } else {
      return '';
    }
  }

  public static function actionLoopItem() {
    if (static::getPrice() >= static::$priceGreaterThanOrEqualTo) {
      echo ' <span class="price francotecnologia_wc_parcpagseg_loop_item_price_span">'
           . (static::getPrice() > 0 ? static::$language['or'] . ' ' : '')
           . static::getParceledValue() . '</span>';
    }
  }

  public static function actionSingleProduct() {
    if (static::getPrice() < static::$priceGreaterThanOrEqualTo) {
      woocommerce_template_single_price();
      return;
    }
    $product = get_product();
    ?>
    <div itemprop="offers" itemscope itemtype="http://schema.org/Offer">
      <p class="price"><?php echo $product->get_price_html(); ?>
        <span class="francotecnologia_wc_parcpagseg_single_product_price_span">
          <?php echo (static::getPrice() > 0 ? static::$language['or'] . ' ' : '') . static::getParceledValue(); ?>
        </span>
      </p>
      <?php
        if ($product->product_type != 'variable') {
          echo static::getParceledTable();
        } else {
          $variationList = $product->get_available_variations();
          foreach($variationList AS $variation) {
            $productVariation = new WC_Product_Variation($variation['variation_id']);
            $defaultVariation = array_diff($variation['attributes'], $product->get_variation_default_attributes());
            echo static::getParceledTable($productVariation->get_price(), $variation['variation_id'], empty($defaultVariation));
          }
        }
      ?>
      <meta itemprop="price" content="<?php echo $product->get_price(); ?>" />
      <meta itemprop="priceCurrency" content="<?php echo get_woocommerce_currency(); ?>" />
      <link itemprop="availability" href="http://schema.org/<?php echo $product->is_in_stock() ? static::$language['InStock'] : static::$language['OutOfStock']; ?>" />
    </div>
    <?php
  }

  public static function actionCart() {
    global $woocommerce;
    if ($woocommerce->cart->total < static::$priceGreaterThanOrEqualTo) {
      return;
    }
    if ($woocommerce->cart->total) {
      $installments = static::getInstallments($woocommerce->cart->total);
    } else {
      $installments = 0;
    }
    if (stripos(static::$language['cartPageMessage'],'%d') !== false) {
      if ($installments > 0) {
        $message = sprintf(static::$language['cartPageMessage'], $installments);
      } else {
        $message = '';
      }
    } else {
      $message = static::$language['cartPageMessage'];
    }
    ?>
    <tr><th colspan="2" class="francotecnologia_wc_parcpagseg_cart_tr_th"><?php echo $message; ?></th></tr>
    <?php
  }

  public static function css() {
    wp_enqueue_style('woocommerce-installments', plugins_url('woocommerce-installments.css', __FILE__), array(), '1.0', 'all');
  }

  public static function js() {
    wp_enqueue_script('woocommerce-installments', plugins_url('woocommerce-installments.js', __FILE__), array('jquery', 'wc-add-to-cart-variation'), '1.0', true);
  }

}

FrancoTecnologiaWooCommerceInstallments::init();