<?php

namespace App\Helpers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\File;

class CareCloudAuth
{
    /**
     * @var CareCloudToken $token
     */
    private CareCloudToken $token;

    public function __construct()
    {
        $this->getTokenFromFile();
        if ($this->token->expires < strtotime("now")) {
            $this->getNewToken();
        }
    }

    /**
     * @return void
     */
    private function getTokenFromFile(): void
    {
        $file = File::get(resource_path("tokens\\token.json"));
        $json = json_decode($file);
        $this->setToken(["token"=>$json->token,"expires"=>$json->expires]);
    }

    /**
     * @return void
     */
    private function getNewToken(): void
    {
        $client = new Client();
        try {
            $response = $client->request("POST", "https://".env("carecloud_url")."/webservice/rest-api/enterprise-interface/v1.0/users/actions/login", [
                "form_params" => [
                    "user_external_application_id" => env("CARECLOUD_EXTERNAL_APPLICATION_ID"),
                    "login" => env("carecloud_login"),
                    "password" => env("carecloud_password")
                ]
            ]);
            $response = json_decode($response->getBody()->getContents());
            $token["token"] = $response->data->bearer_token;
            $token["expires"] = strtotime("+6hours");
            $this->saveTokenToFile($token);
        } catch (GuzzleException) {
        }
    }

    /**
     * @param array $token
     *
     * @return void
     */
    private function saveTokenToFile(array $token): void
    {
        File::put(resource_path("tokens\\token.json"), json_encode($token));
        $this->setToken($token);
    }

    /**
     * @return string
     */
    public function getToken(): string
    {
        $this->getTokenFromFile();
        if ($this->token->expires < strtotime("now")) {
            $this->getNewToken();
        }
        return $this->token->bearerToken;
    }

    /**
     * @param array $token
     *
     * @return void
     */
    private function setToken(array $token): void
    {
        $this->token = new CareCloudToken($token["token"], $token["expires"]);
    }
}
