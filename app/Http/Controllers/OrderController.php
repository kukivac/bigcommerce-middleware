<?php

namespace App\Http\Controllers;

use App\Exceptions\RequestException;
use App\Helpers\CareCloudAuth;
use DateTime;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Laravel\Lumen\Http\ResponseFactory;

/**
 * @group Podnik Endpoints
 */
class OrderController extends Controller
{
    private CareCloudAuth $careCloudAuth;

    public function __construct()
    {
        $this->careCloudAuth = new CareCloudAuth();
    }

    /**
     * @param Request $request
     *
     * @return Response|ResponseFactory
     */
    public function created(Request $request): Response|ResponseFactory
    {
        try {
            $this->validate($request, [
                "data.id" => ["int"]
            ]);
        } catch (ValidationException $exception) {
            return response(["data" => [], "message" => $exception->errors()], 400);
        }
        try {
            $orderData = $this->getOrderData($request->get("data")["id"]);
            $orderData->products = $this->getOrderProducts($orderData->products->link);
            $orderData->customer = $this->getCustomerData($orderData->customer_id);
            $orderData = $this->postOrderToCareCloud($orderData);
            return response(["data" => $orderData, "message" => ""], 200);
        } catch (RequestException $exception) {
            return response(["data" => [], "message" => $exception->getMessage()], $exception->getCode());
        }
    }

    /**
     * @param string $id
     *
     * @return object
     * @throws RequestException
     */
    private function getOrderData(string $id): object
    {
        $client = new Client();
        try {
            $response = $client->request("GET", "https://api.bigcommerce.com/stores/" . env("bigcommerce_store_hash") . "/v2/orders/" . $id, [
                "headers" => [
                    "X-Auth-Token" => env("bigcommerce_token"),
                    "Accept" => "*/*"
                ]
            ]);
            $xml = $response->getBody()->getContents();
            $xml = simplexml_load_string($xml, "SimpleXMLElement", LIBXML_NOCDATA);
            $json = json_encode($xml);
            $orderData = json_decode($json);
            if (!$orderData->payment_status == "paid") {
                throw new RequestException("Order has not yet been paid", 200);
            }
            return $orderData;
        } catch (GuzzleException|Exception) {
            throw new RequestException("Failed request on BigCommerce API", 500);
        }
    }

    /**
     * @param string $link
     *
     * @return object
     * @throws RequestException
     */
    private function getOrderProducts(string $link): object
    {
        $client = new Client();
        try {
            $response = $client->request("GET", "https://api.bigcommerce.com/stores/" . env("bigcommerce_store_hash") . "/v2" . $link, [
                "headers" => [
                    "X-Auth-Token" => env("bigcommerce_token"),
                    "Accept" => "*/*"
                ]
            ]);
            $xml = $response->getBody()->getContents();
            $xml = simplexml_load_string($xml, "SimpleXMLElement", LIBXML_NOCDATA);
            $json = json_encode($xml);
            return json_decode($json);
        } catch (GuzzleException) {
            throw new RequestException("Failed request on BigCommerce API", 500);
        }
    }

    /**
     * @param object $orderData
     *
     * @return object
     * @throws RequestException
     */
    private function postOrderToCareCloud(object $orderData): object
    {
        $token = $this->careCloudAuth->getToken();
        $items = [];
        foreach ($orderData->products as $product) {
            $item = [
                "plu_ids" => [
                    [
                        "list_code" => "GLOBAL",
                        "code" => "p2"
                    ]
                ],
                "plu_name" => $product->name,
                "category_plu_id" => 1,
                "vat_rate" => (float)(100 * ($product->price_inc_tax - $product->price_ex_tax) / $product->price_ex_tax),
                "quantity" => (float)$product->quantity,
                "paid_amount" => (float)$product->total_inc_tax,
                "price" => (float)$product->price_inc_tax,
                "bill_item_id" => $product->id,
                "loyalty_off" => false,
                "purchase_item_type_id" => env("CARECLUD_PURCHASE_ITEM_TYPE_ID")
            ];
            $items[] = $item;
        }
        try {
            $payment_time = new DateTime($orderData->date_created);
        } catch (Exception) {
            throw new RequestException("System exception", 500);
        }
        $data = [
            "store_id" => env("carecloud_store_id"),
            "cashdesk_number" => "1",
            "customer_id" => $orderData->customer->carecloud_id,
            "bill" => [
                "fiscal" => true,
                "purchase_type_id" => env("CARECLOUD_PURCHASE_TYPE_ID"),
                "canceled" => false,
                "payment_type" => "S",
                "bill_id" => $orderData->id,
                "payment_time" => $payment_time->format("c"),
                "currency_id" => $this->getCurrencyId($orderData->store_default_currency_code),
                "total_price" => $orderData->total_inc_tax,
                "bill_items" => $items
            ]
        ];
        $client = new Client();
        try {
            $response = $client->request("POST", "https://" . env("carecloud_url") . "/webservice/rest-api/enterprise-interface/v1.0/purchases/actions/send-purchase", [
                "headers" => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json'
                ],
                "body" => json_encode($data)
            ]);
            return json_decode($response->getBody()->getContents())->data;
        } catch (\GuzzleHttp\Exception\RequestException|GuzzleException $exception) {
            throw new RequestException("Failed request on CareCloud", 500);
        }
    }

    /**
     * @param string $id
     *
     * @return object
     * @throws RequestException
     */
    private function getCustomerData(string $id): object
    {
        $client = new Client();
        try {
            $response = $client->request("GET", "https://api.bigcommerce.com/stores/" . env("bigcommerce_store_hash") . "/v2/customers/" . $id, [
                "headers" => [
                    "X-Auth-Token" => env("bigcommerce_token"),
                    "Accept" => "*/*"
                ]
            ]);
            $xml = $response->getBody()->getContents();
            $xml = simplexml_load_string($xml, "SimpleXMLElement", LIBXML_NOCDATA);
            $json = json_encode($xml);
            $customerData = json_decode($json);
            $customerData->address = $this->getCustomerAddress($customerData->addresses->link);
            $customerData->carecloud_id = $this->getCustomerCareCloudId($customerData);
            return $customerData;
        } catch (GuzzleException) {
            throw new RequestException("Failed request on BigCommerce API", 500);
        }
    }

    /**
     * @param string $link
     *
     * @return object
     * @throws RequestException
     */
    private function getCustomerAddress(string $link): object
    {
        $client = new Client();
        try {
            $response = $client->request("GET", "https://api.bigcommerce.com/stores/" . env("bigcommerce_store_hash") . "/v2" . $link, [
                "headers" => [
                    "X-Auth-Token" => "2ljf6i32abo1mqfx66y8dib3kw5y5nz",
                    "Accept" => "*/*"
                ]
            ]);
            $xml = $response->getBody()->getContents();
            $xml = simplexml_load_string($xml, "SimpleXMLElement", LIBXML_NOCDATA);
            $json = json_encode($xml);
            $customerData = json_decode($json);
            return $customerData->address;
        } catch (GuzzleException) {
            throw new RequestException("Failed request on BigCommerce API", 500);
        }
    }

    /**
     * @param object $customer
     *
     * @return string
     * @throws RequestException
     */
    private function getCustomerCareCloudId(object $customer): string
    {
        $token = $this->careCloudAuth->getToken();
        try {
            $client = new Client();
            $response = $client->request("GET", "https://" . env("carecloud_url") . "/webservice/rest-api/enterprise-interface/v1.0/customer-source-records?customer_source_id=" . env("CARECLOUD_CUSTOMER_SOURCE_ID") . "&external_id=" . $customer->id, [
                "headers" => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json'
                ],
            ]);
            $response = json_decode($response->getBody()->getContents());
            return $response->data->customer_source_records[0]->customer_id;
        } catch (GuzzleException) {
            throw new RequestException("Failed on CareCloud API", 500);
        }
    }

    /**
     * @param string $store_default_currency_code
     *
     * @return string
     * @throws RequestException
     */
    private function getCurrencyId(string $store_default_currency_code): string
    {
        $token = $this->careCloudAuth->getToken();
        $client = new Client();
        try {
            $response = $client->request("GET", "https://" . env("carecloud_url") . "/webservice/rest-api/enterprise-interface/v1.0/currencies", [
                "headers" => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json'
                ]
            ]);
            $response = $response->getBody()->getContents();
            $response = json_decode($response);
            foreach ($response->data->currencies as $currency) {
                if ($currency->code == $store_default_currency_code) {
                    return $currency->currency_id;
                }
            }
            throw new RequestException("Bad reuqest", 400);
        } catch (GuzzleException) {
            throw new RequestException("Failed on CareCloud API", 500);
        }
    }
}
