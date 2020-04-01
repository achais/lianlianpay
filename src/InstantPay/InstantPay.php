<?php

namespace Achais\LianLianPay\InstantPay;

use Achais\LianLianPay\Core\AbstractAPI;
use Achais\LianLianPay\Exceptions\HttpException;
use Achais\LianLianPay\Exceptions\InvalidArgumentException;
use Achais\LianLianPay\Support\Arr;
use Achais\LianLianPay\Support\Collection;
use Achais\LianLianPay\Support\Log;

class InstantPay extends AbstractAPI
{
    const FLAG_CARD_PERSON = '0';
    const FLAG_CARD_ORGANIZE = '1';

    const SIGN_TYPE_RSA = 'RSA';

    private function getAvailableNoOrder()
    {
        return date('YmdHis') . rand(100000, 999999);
    }

    /**
     * 发起一笔付款申请
     *
     * @param string $moneyOrder 付款金额保留小数点后2位,单位元
     * @param string $cardNo 收款方银行账号
     * @param string $acctName 收款方姓名
     * @param string $infoOrder 订单描述。说明付款用途，5W以上必传。
     * @param string $memo 收款备注。 传递至银行， 一般作为订单摘要展示。
     * @param string $flagCard 对公对私标志。
     * @param string $notifyUrl 接收异步通知的线上地址。
     * @param string $bankName 收款银行名称。
     * @param string $prcptcd 大额行号。 可调用大额行号查询接口进行查询。
     * @param string $bankCode 银行编码。 flag_card为1时， 建议选择大额行号+银行编码或开户支行名称+开户行所在市编码+银行编码中的一组传入。
     * @param string $cityCode 开户行所在省市编码， 标准地市编码。
     * @param string $braBankName 开户支行名称
     * @param string $riskItem 风险控制参数。
     * @return Collection|null
     * @throws HttpException
     */
    public function payment($moneyOrder, $cardNo, $acctName, $infoOrder, $memo, $flagCard = self::FLAG_CARD_PERSON,
                            $riskItem = null, $notifyUrl = null, $bankName = null, $prcptcd = null,
                            $bankCode = null, $cityCode = null, $braBankName = null)
    {
        //$url = 'https://instantpay.lianlianpay.com/paymentapi/payment.htm';
        $url = 'https://test.lianlianpay-inc.com/paymentapi/payment.htm';
        $params = [
            "oid_partner" => $this->config['instant_pay.oid_partner'],
            "platform" => $this->config['instant_pay.platform'],
            "api_version" => '1.0',
            "sign_type" => self::SIGN_TYPE_RSA,
            "no_order" => $this->getAvailableNoOrder(),
            "dt_order" => date('YmdHis'),
            "money_order" => $moneyOrder,
            "card_no" => $cardNo,
            "acct_name" => $acctName,
            "info_order" => $infoOrder,
            "flag_card" => $flagCard,
            "memo" => $memo,
            "notify_url" => $notifyUrl ?: $this->config['instant_pay.notify_url'],
            "risk_item" => $riskItem,
            // 以下是对公打款可选参数
            "bank_name" => $bankName,
            "prcptcd" => $prcptcd,
            "bank_code" => $bankCode,
            "city_code" => $cityCode,
            "brabank_name" => $braBankName,
        ];

        $params = $this->buildSignatureParams($params);
        $params = $this->buildPayLoadParams($params);

        return $this->parseJSON('json', [$url, $params]);
    }

    /**
     * 确认付款 (疑似重复订单需要确认付款)
     *
     * @param $noOrder
     * @param $confirmCode
     * @param null $notifyUrl
     * @return Collection|null
     * @throws HttpException
     */
    public function confirmPayment($noOrder, $confirmCode, $notifyUrl = null)
    {
        //$url = 'https://instantpay.lianlianpay.com/paymentapi/confirmPayment.htm';
        $url = 'https://test.lianlianpay-inc.com/paymentapi/confirmPayment.htm';
        $params = [
            "oid_partner" => $this->config['instant_pay.oid_partner'],
            "platform" => $this->config['instant_pay.platform'],
            "api_version" => '1.0',
            "sign_type" => self::SIGN_TYPE_RSA,
            "no_order" => $noOrder,
            "confirm_code" => $confirmCode,
            "notify_url" => $notifyUrl ?: $this->config['instant_pay.notify_url'],
        ];

        $params = $this->buildSignatureParams($params);
        $params = $this->buildPayLoadParams($params);

        return $this->parseJSON('json', [$url, $params]);
    }

    /**
     * @param null $noOrder
     * @param null $oidPayBill
     * @return Collection|null
     * @throws InvalidArgumentException|HttpException
     */
    public function queryPayment($noOrder = null, $oidPayBill = null)
    {
        if (empty($noOrder) && empty($oidPayBill)) {
            throw new InvalidArgumentException('noOrder 和 oidPayBill 不能都为空');
        }

        //$url = 'https://instantpay.lianlianpay.com/paymentapi/queryPayment.htm';
        $url = 'https://test.lianlianpay-inc.com/paymentapi/queryPayment.htm';
        $params = [
            "oid_partner" => $this->config['instant_pay.oid_partner'],
            "sign_type" => self::SIGN_TYPE_RSA,
            "no_order" => $noOrder,
            "platform" => $this->config['instant_pay.platform'],
            "oid_paybill" => $oidPayBill,
            "api_version" => '1.0',
        ];

        $params = $this->buildSignatureParams($params);

        return $this->parseJSON('json', [$url, $params]);
    }

    /**
     * @param array $params
     * @return array
     */
    private function buildSignatureParams($params)
    {
        // 过滤空参数
        $params = Arr::where($params, function ($key, $value) {
            return !is_null($value);
        });
        // 加密
        ksort($params);
        $signRaw = urldecode(http_build_query($params));

        //转换为openssl密钥，必须是没有经过pkcs8转换的私钥
        $res = openssl_get_privatekey($this->getConfig()->getInstantPayPrivateKey());
        //调用openssl内置签名方法，生成签名$sign
        openssl_sign($signRaw, $signStr, $res, OPENSSL_ALGO_MD5);
        //释放资源
        openssl_free_key($res);
        //base64编码
        $params['sign'] =  base64_encode($signStr);;

        return $params;
    }

    /**
     * @param array $params
     * @return array
     */
    private function buildPayLoadParams($params)
    {
        $oidPartner = $this->getConfig()->get('instant_pay.oid_partner');
        $payLoad = LLHelper::encryptPayLoad(json_encode($params), $this->getConfig()->getInstantPayLianLianPublicKey());
        return [
            'oid_partner' => $oidPartner,
            'pay_load' => $payLoad
        ];
    }
}