<?php

namespace JetCamp\JsonApiClient;

/**
 * Guzzle client wrapper for requesting resources from {json:api} APIs
 *
 * Class JsonApiClient
 * @package JetCamp\JsonApiClient
 */
class JsonApiClient
{
    protected $client;
    protected $response;
    protected $token;
    protected $includes = [];
    protected $fields = [];
    protected $filters = [];
    protected $multipart = false;
    protected $query = [];
    protected $limit;
    protected $offset;
    protected $formData;
    protected $jsonData;
    protected $json = [];
    protected $throwException = true;

    public function __construct($client, $token = null)
    {
        $this->client = $client;
        $this->token = $token;
    }

    /**
     * @param array $includes
     * @return $this
     */
    public function withIncludes(array $includes)
    {
        $this->includes = $includes;
        return $this;
    }

    /**
     * @param array $fields
     * @return $this
     */
    public function withFields(array $fields)
    {
        $this->fields = $fields;
        return $this;
    }

    /**
     * @param array $filters
     * @return $this
     */
    public function withFilters(array $filters)
    {
        $this->filters = $filters;
        return $this;
    }

    /**
     * @param array $query
     * @return $this
     */
    public function withQuery(array $query)
    {
        $this->query = $query;
        return $this;
    }

    /**
     * @param $data
     * @return $this
     */
    public function formData($data)
    {
        $this->formData = $data;

        foreach ($data as $d) {
            if ($d instanceof \Illuminate\Http\UploadedFile) {
                $this->multipart = true;
            }
        }

        return $this;
    }

    /**
     * @param $data
     * @return $this
     */
    public function jsonData($data)
    {
        $this->jsonData = $data;
        return $this;
    }

    public function throwException($status = true)
    {
        $this->throwException = $status;
        return $this;
    }

    /**
     * Build query params array
     * @return array
     */
    public function buildQuery()
    {
        $query = [];
        if ($this->query) {
            $query = $this->query;
        }
        if ($this->limit || $this->offset) {
            $query['page'] = [];
            if ($this->limit) {
                $query['page']['limit'] = $this->limit;
            }
            if ($this->offset) {
                $query['page']['offset'] = $this->offset;
            }
        }

        if ($this->filters) {
            foreach ($this->filters as $resource => $columns) {
                if(is_array($columns)){
                    foreach ($columns as $column => $operands) {
                        foreach ($operands as $operand => $value) {
                            $query['filter'][$resource][$column][$operand] = is_array($value) ? implode(',',
                                $value) : $value;
                        }
                    }
                }
                else{
                    $query['filter'][$resource] = $columns;
                }
            }
        }
        if ($this->fields) {
            foreach ($this->fields as $resource => $fieldList) {
                $query['fields'][$resource] = implode(',', $fieldList);
            }
        }
        if ($this->includes) {
            $query['include'] = implode(',', $this->includes);
        }
        return $query;
    }

    public function request($type, $url)
    {
        $params['headers'] = $this->getHeaders();
        $params['query'] = $this->buildQuery();

        if (isset($this->jsonData)) {
            $params['json'] = $this->jsonData;
        }

        if ($this->multipart) {
            $params['multipart'] = $this->convertFormDataIntoMultipart($this->formData);
        } else {
            $params['form_params'] = $this->formData;
        }

        $response = $this->client->request($type, $url, $params);

        if (config('json_api_client.log')) {
            \Log::debug('JSONAPI: ' . $type . ' ' . $url);
        }

        $jsonApiResponse = new JsonApiResponse($response, $this->throwException);
        $jsonApiResponse->prepare();
        return $jsonApiResponse;
    }

    /**
     * @param array $data
     * @return array
     */
    protected function convertFormDataIntoMultipart($data = [])
    {
        $res = [];
        foreach ($data as $name => $value) {
            $row = ['name' => $name];

            if ($value instanceof \Illuminate\Http\UploadedFile) {
                $row['contents'] = fopen($value->path(), 'r');
                $row['filename'] = $value->getClientOriginalName();
            } else {
                $row['contents'] = $value;
            }

            $res[] = $row;
        }
        return $res;
    }

    /**
     * Do a GET request to API
     * @param $url
     * @return JsonApiResponse|null
     */
    public function get($url)
    {
        return $this->request('GET', $url);
    }

    /**
     * Do a POST request to API
     * @param $url
     * @return JsonApiResponse|null
     */
    public function post($url)
    {
        return $this->request('POST', $url);
    }

    /**
     * Do a PATCH request to API
     * @param $url
     * @return JsonApiResponse|null
     */
    public function patch($url)
    {
        return $this->request('PATCH', $url);
    }

    /**
     * Do a DELETE request to API
     * @param $url
     * @return JsonApiResponse|null
     */
    public function delete($url)
    {
        return $this->request('DELETE', $url);
    }

    /**
     * @param $limit
     * @param int $offset
     * @return $this
     */
    public function limit($limit, $offset = 0)
    {
        $this->limit = $limit;
        $this->offset = $offset;
        return $this;
    }

    /**
     * @return array
     */
    private function getHeaders()
    {
        $headers = [];
        if ($this->token) {
            $headers['Authorization'] = 'Bearer ' . $this->token;
        }

        return $headers;
    }

    /**
     * @param $token
     * @return $this
     */
    public function token($token)
    {
        $this->token = $token;

        return $this;
    }
}
