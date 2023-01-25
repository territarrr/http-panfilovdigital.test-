<?
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");
\Bitrix\Main\Loader::includeModule('sale');

class SendForgetProductsEmail
{
    //Id тваров в заказах пользователя за месяц
    static function getProductsFromMonthlyOrders($userID)
    {
        $lastMonthOrders = \Bitrix\Sale\Order::getList([
            'filter' => [
                '>=DATE_INSERT' => \Bitrix\Main\Type\DateTime::createFromTimestamp(time())->add('-1M'),
                'USER_ID' => $userID,
                'LID' => \Bitrix\Main\Context::getCurrent()->getSite()
            ],
            'select' => ['ID']
        ])->fetchAll();

        $productsInLastMonthOrders = [];
        if (count($lastMonthOrders) > 0) {
            foreach ($lastMonthOrders as $lastMonthOrder) {
                $order = \Bitrix\Sale\Order::load((int)$lastMonthOrder['ID']);
                $basket = $order->getBasket();
                foreach ($basket->getBasketItems() as $basketItem) {
                    $productsInLastMonthOrders[] =  (int)$basketItem->getProductId();
                }
            }
        }
        return $productsInLastMonthOrders;
    }


    static function productsToHtml($arProducts)
    {
        $html = '<ul>';
        foreach ($arProducts as $product) {
            $html .= '<li><a href="//' . SITE_SERVER_NAME . $product->getField('DETAIL_PAGE_URL') . '">' . $product->getField('NAME') . '</a></li>';
        }
        $html .= '</ul>';
        return $html;
    }

    static function sendEmails()
    {
        $arUsers = \Bitrix\Main\UserTable::getList([
            'select' => ['ID', 'EMAIL', 'NAME', 'LAST_NAME'],
            'filter' => ['ACTIVE' => 'Y']
        ])->fetchAll();

        foreach ($arUsers as $arUser) {
            $productsInLastMonthOrders = static::getProductsFromMonthlyOrders($arUser['ID']);
            $productsToSend = [];
            $basket = \Bitrix\Sale\Basket::loadItemsForFUser(
                \Bitrix\Sale\Fuser::getId($arUser['ID']),
                \Bitrix\Main\Context::getCurrent()->getSite()
            );

            foreach ($basket->getBasketItems() as $basketItem) {
                if (!in_array((int)$basketItem->getProductId(), $productsInLastMonthOrders)) {
                    $productsToSend[] = $basketItem;
                }
            }

            if (count($productsToSend) > 0) {
                CEvent::Send("SEND_FORGORT_PRODUCTS", \Bitrix\Main\Context::getCurrent()->getSite(), [
                    'USER_EMAIL' => $arUser['EMAIL'],
                    'USER_NAME' => $arUser['NAME'] . ' ' . $arUser['LAST_NAME'],
                    'PRODUCT_LIST' => static::productsToHtml($productsToSend),
                ]);
            }
        }
    }
}

SendForgetProductsEmail::sendEmails();
