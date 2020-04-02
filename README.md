<h1 align="center"> LianLianPay </h1>

<p align="center"> 连连支付 SDK for PHP..</p>

<p align="center"> 让你忽略第三方的 Http 请求规则、加密方式和加密实现, 只需关注自己的业务代码</p>


## 安装

```shell
$ composer require achais/lianlianpay:dev-master -vvv
```

## 使用
配置信息和实例化
```php
use Achais\LianLianPay\LianLianPay;

$config = [
    'debug' => true, // 开启调试

    // 实时付款参数
    'instant_pay' => [
        'oid_partner' => env('LLP_IP_OID_PARTNER'), // 商户号
        'private_key' => env('LLP_IP_PRIVATE_KEY'), // 商户私钥
        'public_key' => env('LLP_IP_PUBLIC_KEY'), // 商户公钥
        'll_public_key' => env('LLP_IP_LL_PUBLIC_KEY'), // 连连支付公钥
        'production' => env('LLP_IP_PRODUCTION', false), // 是否生产环境
        'notify_url' => 'http://localhost/', // 付款结果异步回调地址
    ],

    // 日志
    'log' => [
        'level' => 'debug',
        'permission' => 0777,
        'file' => '/tmp/logs/lianlianpay-' . date('Y-m-d') . '.log', // 日志文件, 你可以自定义
    ],
];

$llp = new LianLianPay($config);
```
> 不管使用什么功能, 配置信息和实例化 LianLianPay 是必须的

### 实时付款

实时付款功能服务提供者`\Achais\LianLianPay\InstantPay\InstantPay`所有方法和参数可看源码.

#### 付款申请
```php
use Achais\LianLianPay\LianLianPay;

$config = []; // 配置信息如上
$llp = new LianLianPay($config);

$moneyOrder = '0.02'; // 付款金额
$cardNo ='6212261203****'; // 收款卡号
$acctName = '起风'; // 收款人姓名
$infoOrder = '代付'; // 订单描述。说明付款用途，5W以上必传。
$memo = '余额提现'; // 收款备注。 传递至银行， 一般作为订单摘要展示。

$ret = $llp->instantPay->payment($moneyOrder, $cardNo, $acctName, $infoOrder, $memo); // 付款申请

结果:
Collection {#287 ▼
  #items: array:7 [▼
    "confirm_code" => "836765"
    "no_order" => "202004021****"
    "oid_partner" => "20190902****"
    "ret_code" => "4002"
    "ret_msg" => "疑似重复提交订单"
    "sign" => "W4Y4R6rjyWJYupN508Q5E1****"
    "sign_type" => "RSA"
  ]
}
```
> 只要不是报 HttpException 都是请求成功, 返回码 0000 一次性提交成功, 返回码 4002, 4003, 4004 是疑似重复提交订单需要二次确认付款, 见下一个接口

#### 确认付款
```php
use Achais\LianLianPay\LianLianPay;

$config = []; // 配置信息
$llp = new LianLianPay($config);

$noOrder = '202004021****'; // 付款申请接口返回的 商户订单号
$confirmCode = '836765'; // 付款申请接口返回的 确认码
$ret = $llp->instantPay->confirmPayment($noOrder, $confirmCode); // 确认付款

结果:
Collection {#288 ▼
  #items: array:7 [▼
    "no_order" => "2020040210****"
    "oid_partner" => "201909021****"
    "oid_paybill" => "20200402****"
    "ret_code" => "0000"
    "ret_msg" => "交易成功"
    "sign" => "dU0gfuAoevbuMcWb5k9HnoE****"
    "sign_type" => "RSA"
  ]
}

```
> 疑似重复提交订单经过确认付款才是真的提交成功, 但是.. 不代表付款成功, 付款结果需要你主动拿商户订单号去查询, 或者等待连连支付异步回调

### 付款结果查询
付款结果查询接口适用于异常情况或者订单处于半小时还没通知的情况, 正常情况由连连异步通知修改订单状态

付款结果查询接口返回`ret_code=0000`时根据订单状态处理，返回`ret_code=8901`没有记录。

```php
use Achais\LianLianPay\LianLianPay;

$config = []; // 配置信息
$llp = new LianLianPay($config);

$noOrder = '2020040210****'; // 商户订单号
$ret = $llp->instantPay->queryPayment($noOrder); // 付款结果查询

结果:
Collection {#289 ▼
  #items: array:12 [▼
    "dt_order" => "2020040210****"
    "info_order" => "代付"
    "money_order" => "0.02"
    "no_order" => "202004021****"
    "oid_partner" => "201909021****"
    "oid_paybill" => "20200402****"
    "result_pay" => "SUCCESS"
    "ret_code" => "0000"
    "ret_msg" => "交易成功"
    "settle_date" => "20200402"
    "sign" => "bvKk+mfPwuwXRwNwZEi****"
    "sign_type" => "RSA"
  ]
}
```
> 查询时间距离付款请求时间半个小时返回`ret_code=8901`无记录才能置为失败。  
> 付款结果， 结果以 result_pay 字段为准， 详情可参考付款类订单状态说明。

## 文档

更多功能介绍请看源码或 Wiki.

## 贡献

You can contribute in one of three ways:

1. File bug reports using the [issue tracker](https://github.com/achais/lianlianpay/issues).
2. Answer questions or fix bugs on the [issue tracker](https://github.com/achais/lianlianpay/issues).
3. Contribute new features or update the wiki.

_The code contribution process is not very formal. You just need to make sure that you follow the PSR-0, PSR-1, and PSR-2 coding guidelines. Any new code contributions must be accompanied by unit tests where applicable._

## License

MIT