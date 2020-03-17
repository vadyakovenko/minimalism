<?php
namespace carlonicora\minimalism\jsonapi\resources;

use carlonicora\minimalism\jsonapi\traits\linksTrait;
use carlonicora\minimalism\jsonapi\traits\metaTrait;

class resourceRelationship {
    use linksTrait;
    use metaTrait;

    /** @var resourceObject  */
    public resourceObject $data;

    /**
     * resourceRelationship constructor.
     * @param resourceObject $resource
     */
    public function __construct(resourceObject $resource) {
        $this->data = $resource;
    }

    /**
     * @param bool $includesAttributes
     * @return array
     */
    public function toArray(bool $includesAttributes=true) : array {
        $response = [
            'data' => $this->data->toArray($includesAttributes)
        ];

        if ($this->links !== null){
            $response['links'] = $this->links;
        }

        if ($this->meta) {
            $response['meta'] = $this->meta;
        }

        return $response;
    }
}