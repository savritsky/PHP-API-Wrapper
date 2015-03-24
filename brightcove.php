<?php

require_once 'vendor/autoload.php';

class BrightcoveClient {
  protected $access_token;

  public function __construct($access_token) {
    $this->access_token = $access_token;
  }

  public function isAuthorized() {
    return !!$this->access_token;
  }

  public static function authorize($client_id, $client_secret) {
    $client = new GuzzleHttp\Client();
    $res = $client->post('https://oauth.brightcove.com/v3/access_token', [
      'body' => 'grant_type=client_credentials',
      'headers' => [
        'Content-Type' => 'application/x-www-form-urlencoded',
      ],
      'auth' => [$client_id, $client_secret],
    ]);

    if ($res->getStatusCode() !== 200) {
      throw new BrightcoveAuthenticationException();
    }

    $json = $res->json();
    if ($json['token_type'] !== 'Bearer') {
      throw new BrightcoveAuthenticationException('Unsupported token type: ' . $json['token_type']);
    }

    return new BrightcoveClient($json['access_token']);
  }

  public function request($method, $api_type, $account, $endpoint, $result, $post = NULL) {
    $client = new GuzzleHttp\Client();
    $body = NULL;
    if ($post) {
      $body = json_encode($post);
      $body = json_decode($body, TRUE);
      $body = array_filter($body, function ($item) {
        return !is_null($item);
      });
      $body = json_encode($body);
    }
    $res = $client->{strtolower($method)}("https://{$api_type}.api.brightcove.com/v1/accounts/{$account}{$endpoint}", [
      'headers' => [
        'Authorization' => "Bearer {$this->access_token}",
      ],
      'body' => $body,
    ]);

    $code = $res->getStatusCode();
    if ($code < 200 || $code >= 300) {
      throw new BrightcoveAPIException('Invalid status code: expected 200-299, got ' . $res->getStatusCode());
    }

    $json = $res->json();
    $mapper = new JsonMapper();
    if (is_null($result)) {
      return $json;
    } else if (is_object($result)) {
      return $mapper->map($json, $result);
    } else {
      return $mapper->mapArray($json, [], $result);
    }
  }
}

class BrightcoveAPI {
  protected $account;
  protected $client;

  public function __construct(BrightcoveClient $client, $account) {
    $this->client = $client;
    $this->account = $account;
  }
}

class BrightcoveAPIException extends Exception {}
class BrightcoveAuthenticationException extends Exception {}
