<?php

namespace App\Http\Controllers;

use App\Exceptions\RequestException;
use App\Helpers\CareCloudAuth;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use JetBrains\PhpStorm\ArrayShape;
use Laravel\Lumen\Http\ResponseFactory;

/**
 * @group Default Voucher Endpoints
 */
class CustomerController extends Controller
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
            $customerData = $this->getCustomerData($request->get("data")["id"]);
            $customerData->address = $this->getCustomerAddress($customerData->addresses->link);
            $customerData = $this->postCustomerToCareCloud($customerData);
            return response(["data" => ["customer" => $customerData], "message" => ""], 200);
        } catch (RequestException $exception) {
            return response(["data" => [], "message" => $exception->getMessage()], $exception->getCode());
        }
    }

    /**
     * @param Request $request
     *
     * @return Response|ResponseFactory
     */
    public function updated(Request $request): Response|ResponseFactory
    {
        try {
            $this->validate($request, [
                "data.id" => ["int"]
            ]);
        } catch (ValidationException $exception) {
            return response(["data" => [], "message" => $exception->errors()], 400);
        }
        try {
            $customerCareCloudId = $this->getCustomerCareCloudId($request->get("data")["id"]);
            $customerData = $this->getCustomerData($request->get("data")["id"]);
            $customerData->address = $this->getCustomerAddress($customerData->addresses->link);
            $customerData = $this->putCustomerToCareCloud($customerCareCloudId, $customerData);
            return response(["data" => ["customer" => $customerData], "message" => ""], 200);
        } catch (RequestException $exception) {
            return response(["data" => [], "message" => $exception->getMessage()], $exception->getCode());
        }
    }

    /**
     * @param $id
     *
     * @return object
     * @throws RequestException
     */
    private function getCustomerData($id): object
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
            return json_decode($json);
        } catch (GuzzleException $exception) {
            throw new RequestException("Failed request on BigCommerce API", 500);
        }
    }

    /**
     * @param $customerData
     *
     * @return object
     * @throws RequestException
     */
    private function postCustomerToCareCloud($customerData): object
    {
        $token = $this->careCloudAuth->getToken();
        $data = $this->makeCustomerData($customerData);
        $client = new Client();
        try {
            $response = $client->request("POST", "https://" . env("carecloud_url") . "/webservice/rest-api/enterprise-interface/v1.0/customers", [
                "headers" => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json'
                ],
                "body" => json_encode($data)
            ]);
            //testing
            dump(json_decode($response->getBody()->getContents()));
            //testing
            return json_decode($response->getBody()->getContents());
        } catch (\GuzzleHttp\Exception\RequestException $exception) {
            //testing
            $exception = json_decode($exception->getResponse()->getBody()->getContents());
            //testing
            if ($exception->error->error_data->invalid_params[0]->message == "Contact source already exists") {
                throw new RequestException("Customer already exists", 500);
            }
            throw new RequestException("Failed on CareCloud API", 500);
        } catch (GuzzleException $exception) {
            throw new RequestException("Failed on CareCloud API", 500);
        }
    }

    /**
     * @param $link
     *
     * @return object
     * @throws RequestException
     */
    private function getCustomerAddress($link): object
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
        } catch (GuzzleException $exception) {
            throw new RequestException("Failed on BigCommerce API", 500);
        }
    }

    /**
     * @throws RequestException
     */
    private function getCustomerCareCloudId(string $id)
    {
        $token = $this->careCloudAuth->getToken();
        try {
            $client = new Client();
            $response = $client->request("GET", "https://" . env("carecloud_url") . "/webservice/rest-api/enterprise-interface/v1.0/customer-source-records?customer_source_id=" . env("CARECLOUD_CUSTOMER_SOURCE_ID") . "&external_id=" . $id, [
                "headers" => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json'
                ],
            ]);
            $response = json_decode($response->getBody()->getContents());
            return $response->data->customer_source_records[0]->customer_id;
        } catch (GuzzleException $exception) {
            throw new RequestException("Failed on CareCloud API", 500);
        }
    }

    /**
     * @throws RequestException
     */
    private function putCustomerToCareCloud(string $customerCareCloudId, object $customerData): object
    {

        $token = $this->careCloudAuth->getToken();
        $data = $this->makeCustomerData($customerData);
        $client = new Client();
        try {
            $response = $client->request("PUT", "https://" . env("carecloud_url") . "/webservice/rest-api/enterprise-interface/v1.0/customers/" . $customerCareCloudId, [
                "headers" => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json'
                ],
                "body" => json_encode($data)
            ]);
            if($response->getStatusCode()==204){
                throw new RequestException("",200);
            }
            return json_decode($response->getBody()->getContents());
        } catch (GuzzleException $exception) {
            throw new RequestException("Failed on CareCloud API", 500);
        }
    }

    /**
     * @param object $customerData
     *
     * @return array
     */
    #[ArrayShape(["customer" => "array[]", "customer_source" => "array", "autologin" => "false"])]
    private function makeCustomerData(object $customerData): array
    {
        return [
            "customer" => [
                "personal_information" => [
                    "first_name" => $customerData->first_name,
                    "last_name" => $customerData->last_name,
                    "email" => $customerData->email,
                    "phone" => (int)str_replace("+", "", $customerData->phone),
                    "language_id" => "cs",
                    "address" => [
                        "address1" => $customerData->address->street_1,
                        "address2" => $customerData->address->street_2,
                        "zip" => $customerData->address->zip,
                        "city" => $customerData->address->city
                    ]
                ]
            ],
            "customer_source" => [
                "customer_source_id" => env("carecloud_customer_source_id"),
                "external_id" => $customerData->id
            ],
            "autologin" => false
        ];
    }
}
