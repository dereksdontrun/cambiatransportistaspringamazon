<?php
/**
* 2007-2020 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2020 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

class Cambiatransportistaspringamazon extends Module
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'cambiatransportistaspringamazon';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Sergio';
        $this->need_instance = 0;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Cambia Transportista de Spring en pedidos Amazon / Estado Vendido sin Stock');
        $this->description = $this->l('Cambia Transportista de Spring TRACKED a Spring SIGNATURED en pedidos Amazon de valor superior a una cantidad fijada (más de 30€). También cambia el estado de Pago aceptado a Verificando Stock "PS_OS_OUTOFSTOCK_PAID" los pedidos Amazon de productos vendidos sin stock.');

        $this->confirmUninstall = $this->l('¿Deseas desinstalar este módulo de verdad?');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        Configuration::updateValue('CAMBIATRANSPORTISTASPRINGAMAZON_LIVE_MODE', false);
        Configuration::updateValue('CAMBIATRANSPORTISTASPRINGAMAZON_PAGADO_LIMITE', false);
        Configuration::updateValue('CAMBIATRANSPORTISTASPRINGAMAZON_CAMBIO_ESTADO', false);

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('backOfficeHeader') && 
            $this->registerHook('actionObjectOrderCarrierAddAfter') && //hook que se llama después de la creación del objeto OrderCarrier
            $this->registerHook('actionOrderStatusPostUpdate');  //hook que se llama después de un cambio de estado de pedido
    }

    public function uninstall()
    {
        Configuration::deleteByName('CAMBIATRANSPORTISTASPRINGAMAZON_LIVE_MODE');
        Configuration::deleteByName('CAMBIATRANSPORTISTASPRINGAMAZON_PAGADO_LIMITE');
        Configuration::deleteByName('CAMBIATRANSPORTISTASPRINGAMAZON_CAMBIO_ESTADO');

        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool)Tools::isSubmit('submitCambiatransportistaspringamazonModule')) == true) {
            $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

        return $output.$this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitCambiatransportistaspringamazonModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                'title' => $this->l('CONFIGURACIÓN'),
                'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Asignar Spring SIGNATURED a pedidos Amazon con Spring TRACKED'),
                        'name' => 'CAMBIATRANSPORTISTASPRINGAMAZON_LIVE_MODE',
                        'is_bool' => true,
                        'desc' => $this->l('Asignar Spring SIGNATURED a pedidos Amazon con Spring TRACKED cuyo valor sea superior a una cantidad'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-euro"></i>',
                        'desc' => $this->l('Introduce el valor del pedido a partir del cual se cambiará el transportista de dicho pedido. Si el destino es Francia siempre se asigna SIGNATURED'),
                        'name' => 'CAMBIATRANSPORTISTASPRINGAMAZON_PAGADO_LIMITE',
                        'label' => $this->l('Total Pagado'),
                    ),  
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Cambiar pedidos Amazon vendidos sin stock de Pago Aceptado a Verificando Stock'),
                        'name' => 'CAMBIATRANSPORTISTASPRINGAMAZON_CAMBIO_ESTADO',
                        'is_bool' => true,
                        'desc' => $this->l('Los pedidos aceptados en Amazon cuyo contenido son productos con stock "forzado" entrarán por defecto en Pago Aceptado, seleccionando SI se cambiará su estado a Verificando Stock (Sin stock Pagado)'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),                  
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            'CAMBIATRANSPORTISTASPRINGAMAZON_LIVE_MODE' => Configuration::get('CAMBIATRANSPORTISTASPRINGAMAZON_LIVE_MODE'),
            'CAMBIATRANSPORTISTASPRINGAMAZON_PAGADO_LIMITE' => Configuration::get('CAMBIATRANSPORTISTASPRINGAMAZON_PAGADO_LIMITE'),             
            'CAMBIATRANSPORTISTASPRINGAMAZON_CAMBIO_ESTADO' => Configuration::get('CAMBIATRANSPORTISTASPRINGAMAZON_CAMBIO_ESTADO'),            
        );
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            //guardamos en BD ,tabla configuration la configuración del módulo
            if ($key == 'CAMBIATRANSPORTISTASPRINGAMAZON_LIVE_MODE'){
                Configuration::updateValue($key, Tools::getValue($key));

            } elseif ($key == 'CAMBIATRANSPORTISTASPRINGAMAZON_PAGADO_LIMITE'){
                //aseguramos que el precio tenga formato con separador decimal punto
                $pagado_limite = str_replace(',','.', Tools::getValue($key));
                //comprobamos que es un número
                if (is_numeric($pagado_limite)){                    
                    Configuration::updateValue($key, $pagado_limite);
                }

            } elseif ($key == 'CAMBIATRANSPORTISTASPRINGAMAZON_CAMBIO_ESTADO'){
                Configuration::updateValue($key, Tools::getValue($key));
            }            
        }
    }

    /**
    * Add the CSS & JavaScript files you want to be loaded in the BO.
    */
    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('module_name') == $this->name) {
            $this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path.'/views/js/front.js');
        $this->context->controller->addCSS($this->_path.'/views/css/front.css');
    }

    // $params contiene el objeto orderCarrier en este caso, $params['object'], es decir, $params['object']->id sería el id de la tabla order_carrier
    public function hookActionObjectOrderCarrierAddAfter($params) 
    {
        //Comprobamos la configuración del módulo, si está activo para el cambio de transportista
        if (!Configuration::get('CAMBIATRANSPORTISTASPRINGAMAZON_LIVE_MODE')){
            return;
        }
        //El hook funcionará cada vez que entra un nuevo pedido.
        if ($params) {
            //sacamos el pedido
            $order_carrier = $params['object'];
            if (Validate::isLoadedObject($order_carrier))
            {                          

                $id_order = (int)$order_carrier->id_order;

                $order = new Order($id_order);

                if (!Validate::isLoadedObject($order)) {
                    return;
                }

                //comprobamos primero que sea un pedido de amazon internacional
                if ($order->module != 'amazon') {
                    return;
                }

                //buscamos el marketplace del que viene el pedido
                $sql_marketplace_amazon = 'SELECT sales_channel FROM lafrips_marketplace_orders WHERE id_order = '.$id_order;
                $marketplace_amazon = Db::getInstance()->executeS($sql_marketplace_amazon)[0]['sales_channel'];

                //Si no obtenemos resultado se da por erróneo
                if (!$marketplace_amazon) {
                    return;
                }                

                //sacamos el país de destino desde la dirección, que puede no coincidir con el marketplace (comprar en .de pero enviar a España, haría que el transportista fuese Spring, y si fuera de más de x euros, este módulo lo pasaría a Signatured, pero en realidad queremos enviarlo con GLS)
                $id_address = $order->id_address_delivery;
                //instanciamos dirección para sacar el id_country
                $address = new Address($id_address);
                if (!Validate::isLoadedObject($address)) {
                    return;
                }
                $id_country = $address->id_country;

                //si el país de la dirección cuenta como península (España id_country 6, Portugal id_country 15, ¿Andorra?) y el marketplace NO es amazon.es, cambiamos a GLS Domicilio
                // 12/04/2021 Ya no envíamos a Portugal como si fuera península de modo que lo quitamos de aquí. Por defecto con el módulo Amazon entrarán con GLS Europa, pero si lo han comprado y va con Spring lo permitimos. Lo dejamos para compras a España que no sean de amazon.es
                // if (($marketplace_amazon != 'Amazon.es') && (($id_country == 6) || ($id_country == 15))) {
                if (($marketplace_amazon != 'Amazon.es') && ($id_country == 6)) {
                    //sacamos el id_carrier útil de GLS domicilio
                    $id_reference_gls = Configuration::get('GLS_SERVICIO_SELECCIONADO_GLSECO');
                    $id_GLS_Domicilio = Db::getInstance()->getValue('SELECT id_carrier FROM lafrips_carrier WHERE active = 1 AND deleted = 0 AND id_reference = '.$id_reference_gls.' ORDER BY id_carrier DESC');
                    
                    //id_carrier en lafrips_orders
                    $id_carrier_orders = $order->id_carrier;  
                    //id_carrier en lafrips_ordercarrier
                    $id_carrier_ordercarrier = $order_carrier->id_carrier; 
                    if (!$id_carrier_orders || !$id_carrier_ordercarrier  || ($id_carrier_ordercarrier  != $id_carrier_orders) || ($id_carrier_orders == $id_GLS_Domicilio)) {
                        return;
                    }
                    //cumplidas todas las condiciones hacemos el cambio a GLS Domicilio
                    $order->id_carrier = (int) $id_GLS_Domicilio;
                    $order_carrier->id_carrier = (int) $id_GLS_Domicilio;
                    $order->update();  
                    $order_carrier->update();  

                    //una vez cambiado el transportista salimos
                    return;
                }

                //22/06/2021 Ahora enviamos a Portugal con MRW. Si el país de destino es id 15, independientemente del marketplace, ponemos MRW como transporte
                if ($id_country == 15) {
                    //sacamos el id_carrier útil de MRW desde la tabla de configuración
                    $id_mrw = (int) Configuration::get('MRWCARRIER_CARRIER_ID_MRW');                    
                    //id_carrier en lafrips_orders
                    $id_carrier_orders = $order->id_carrier;  
                    //id_carrier en lafrips_ordercarrier
                    $id_carrier_ordercarrier = $order_carrier->id_carrier; 
                    if (!$id_carrier_orders || !$id_carrier_ordercarrier  || ($id_carrier_ordercarrier  != $id_carrier_orders) || ($id_carrier_orders == $id_mrw)) {
                        return;
                    }
                    //cumplidas todas las condiciones hacemos el cambio a MRW
                    $order->id_carrier = $id_mrw;
                    $order_carrier->id_carrier = $id_mrw;
                    $order->update();  
                    $order_carrier->update();  

                    //una vez cambiado el transportista salimos
                    return;
                }

                //si no se ha dado el caso de compra en marketplace no .es pero envío a España, seguimos con Spring
                //Comprobamos la configuración del módulo, sacamos el pago límite de los pedidos a partir del que cambiar el transportista
                if (!empty(Configuration::get('CAMBIATRANSPORTISTASPRINGAMAZON_PAGADO_LIMITE'))){
                    $pagado_limite = (int)Configuration::get('CAMBIATRANSPORTISTASPRINGAMAZON_PAGADO_LIMITE');
                }else{
                    $pagado_limite = 30;
                }

                //sacamos el total paid del pedido
                $pagado = $order->total_paid;

                //si el marketplace es UK imaginamos un cambio de libra a euro de 1.15 para establecer total_paid, ya que el dinero de pago será libras y el calculo será diferente                
                if ($marketplace_amazon == 'Amazon.co.uk') {
                    $pagado = $pagado*1.15;
                }

                //si pagado no es superior a $pagado_limite salimos
                //24/10/2022 Queremos que si la entrega es en Francia siempre vaya con Signed, independientemente del coste del pedido, de modo que si $pagado no pasa del límite, además el país de entrega no debe ser Francia para que podamos continuar $id_country = 8
                if (($pagado < $pagado_limite) && ($id_country != 8)){
                    return;
                }
                
                //id_carrier en lafrips_orders
                $id_carrier_orders = $order->id_carrier;                

                //sacamos el id_carrier válido actualmente correspondiente a Spring TRACKED buscando por el nombre del servicio en lafrips_configuration, que nos da id_reference
                $id_reference_spring_tracked = Configuration::get('springxbs_TRCK');
                $id_spring_TRACKED = Db::getInstance()->getValue('SELECT id_carrier FROM lafrips_carrier WHERE active = 1 AND deleted = 0 AND id_reference = '.$id_reference_spring_tracked.' ORDER BY id_carrier DESC');

                //comprobamos que hemos obtenido un id_carrier para TRACKED y que sea el mismo que el del pedido que acaba de entrar
                if (!$id_spring_TRACKED || ($id_spring_TRACKED != $id_carrier_orders)) {
                    return;
                }

                //sacamos el id_carrier válido actualmente correspondiente a Spring SIGNATURED buscando por el nombre del servicio en lafrips_configuration, que nos da id_reference
                $id_reference_spring_signed = Configuration::get('springxbs_SIGN');
                $id_spring_SIGNATURED = Db::getInstance()->getValue('SELECT id_carrier FROM lafrips_carrier WHERE active = 1 AND deleted = 0 AND id_reference = '.$id_reference_spring_signed.' ORDER BY id_carrier DESC');
                
                //si la búsqueda no da resultado salimos
                if (!$id_spring_SIGNATURED) {
                    return;
                }    

                // $id_ordercarrier = $order->getIdOrderCarrier();                      

                //id_carrier en lafrips_ordercarrier
                $id_carrier_ordercarrier = $order_carrier->id_carrier;               

                //si id_carrier no coincide en orders y ordercarrier salimos, etc
                if (!$id_carrier_ordercarrier || ($id_carrier_orders != $id_carrier_ordercarrier)) {
                    return;
                }                    
                
                // $realizado = 'Trabajo sobre producto ID = '.$order->id.' module='.$order->module.' total_paid= '.$order->total_paid.' <br> $pagado_limite='.$pagado_limite.' $id_carrier_orders='.$id_carrier_orders.' $id_spring_TRACKED='.$id_spring_TRACKED.' $id_spring_SIGNATURED='.$id_spring_SIGNATURED.' $id_carrier_ordercarrier='.$id_carrier_ordercarrier;
                // //debug - quitar 
                // file_put_contents('/var/www/vhost/lafrikileria.com/home/html/tmpAuxiliar/pruebas_hook_objectorder2_'.date('dmY_h:i:s').'.txt', print_r($realizado, true));  

                //esta condición debe darse si ha llegado hasta aquí pero ... Asignamos y hacemos update del id_carrier nuevo
                if ($id_spring_SIGNATURED != $id_carrier_ordercarrier) {
                    $order->id_carrier = (int) $id_spring_SIGNATURED;
                    $order_carrier->id_carrier = (int) $id_spring_SIGNATURED;
                    $order->update();  
                    $order_carrier->update();                    
                } else { 
                    return;
                }       
             
            }
        }
    }

    //07/03/2023 Añadimos hook para cazar los pedidos amazon que contienen productos vendidos sin stock. La configuración del módulo solo permite esatblecer un estado de entrada de pedidos completos, Pago Aceptado en este caso, pero si enviamos y vendemos productos con stock "forzado" entran en prestashop en el mismo estado. Con este hook comprobamos si un pedido entrante en estado pago aceptado es de amazon y si lo es comprobamos si los productos tienen stock suficiente. Si no es así lo pasamos a verificando stock (out of stock paid)
    public function hookActionOrderStatusPostUpdate($params)  {
        //Comprobamos la configuración del módulo, si está activo para el cambio de estado
        if (!Configuration::get('CAMBIATRANSPORTISTASPRINGAMAZON_CAMBIO_ESTADO')){
            return;
        }

        //El hook funcionará cada vez que entra un nuevo pedido.
        if ($params) {
            $id_order = (int)$params['id_order'];
            $newOrderStatus = $params['newOrderStatus'];
            $id_employee = $params['id_employee'];
            // $oldOrderStatus = $params['oldOrderStatus'];            
            // $orderHistory = $params['orderHistory'];

            //para este proceso el pedido tiene que entrar en Pago Aceptado
            if ((int)$newOrderStatus->id !== (int)Configuration::get('PS_OS_PAYMENT')) {
                return;
            }

            $order = new Order($id_order);

            if (!Validate::isLoadedObject($order)) {
                return;
            }

            //comprobamos primero que sea un pedido de amazon internacional
            if ($order->module != 'amazon') {
                return;
            }

            //el pedido es Amazon y entra en Pago Aceptado. Tenemos que comprobar si el/los productos que contiene tienen stock suficiente disponible. Para ello llamamos a la función checkOrderStock() que devolverá true o false
            if ($this->checkOrderStock($order)) {
                //hay stock suficiente, se queda en pago aceptado
                return;
            }

            //no hay stock suficiente, pasamos el pedido a Verificando Stock / Sin Stock Pagado
            $id_verificando_stock = (int)Configuration::get('PS_OS_OUTOFSTOCK_PAID');

            $new_history = new OrderHistory();
            $new_history->id_order = $id_order;
            $new_history->id_employee = $id_employee;
            $use_existing_payment = !$order->hasInvoice();
            $new_history->changeIdOrderState($id_verificando_stock, $id_order, $use_existing_payment);
            // $history->addWithemail();
            $new_history->add(true);
            $new_history->save(); 

            //hacemos un LOG
            $estado_inicial = (int)$newOrderStatus->id;

            $insert_frik_pedidos_cambiados = "INSERT INTO frik_pedidos_cambiados 
            (id_order, estado_inicial, estado_final, proceso, date_add) 
            VALUES ($id_order ,
            $estado_inicial ,
            $id_verificando_stock ,            
            'A Verificando Stock - Amazon Sin Stock - Automático',
            NOW())";

            if (Db::getInstance()->Execute($insert_frik_pedidos_cambiados)) {
                return;
            } 

            return;
        }
    }

    //función que devuelve true o false en función de si el pedido entrante dispone de stock suficiente disponible para todos sus productos.
    public function checkOrderStock($order)  {
        //como el pedido ya está dentro de Prestashop no vale comprobar los datos del carrito, hay que sacar orderdetail y comprobar el campo product_quuantity_in_stock. Amazon establece ese campo al crear el pedido de forma diferente a Prestashop. En prestashop es 
        //$product_quantity = (int)Product::getQuantity($this->product_id, $this->product_attribute_id);
        //$this->product_quantity_in_stock = ($product_quantity - (int)$product['cart_quantity'] < 0) ? $product_quantity : (int)$product['cart_quantity'];
        //Es decir, al stock disponible (total) se le restan las unidades del carrito, si da negativo se pone el stock disponible, si no es negativo se pone la cantidad en carrito.
        //el módulo de Amazon (y mirakl creo) hace:
        //$productQuantity = Product::getRealQuantity($id_product, $id_product_attribute, $id_warehouse, $order->id_shop);
        //$quantityInStock = $productQuantity - $product['cart_quantity'];
        //$order_detail->product_quantity_in_stock = (int)$quantityInStock;
        //Es decir, obtiene el stock real, que es real qty = actual qty in stock - current client orders + current supply orders, a eso le resta la cantidad en carrito, es decir, si el producto no tiene stock dará negativo al entrar el pedido. Puede haber errores provocados por tener el producto en pedidos de materiales¿?

        //sacamos los datos de productos en pedido, esto deveulve básicamente el contenido de order_detail, product y product_shop para los productos del pedido
        $order_products = $order->getProducts(); 

        foreach ($order_products as $order_product){ 
            //si el producto no está en gestión avanzada, pasamos al siguiente
            if (!StockAvailableCore::dependsOnStock($order_product['product_id'])){                
                continue;
            }           

            //si algún producto tiene valor negativo en product_quantity_in_stock es que debe entrar en Verificando stock (para pedidos creados con módulo de amazon, prestashop es diferente)
            if ($order_product['product_quantity_in_stock'] < 0) {
                return false;
            }
        }

        return true;
    }

}
