<?php

namespace CloudCreativity\JsonApi\Http\Responses;

use CloudCreativity\JsonApi\Contracts\Integration\EnvironmentInterface;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Neomerx\JsonApi\Contracts\Encoder\EncoderInterface;
use Neomerx\JsonApi\Contracts\Responses\ResponsesInterface;

/**
 * Class ResponsesHelper
 * @package CloudCreativity\JsonApi
 */
class ResponsesHelper
{

    /**
     * @var EnvironmentInterface
     */
    private $environment;

    /**
     * @var ResponsesInterface
     */
    private $responses;

    /**
     * @param EnvironmentInterface $environment
     * @param ResponsesInterface $responses
     */
    public function __construct(EnvironmentInterface $environment, ResponsesInterface $responses)
    {
        $this->environment = $environment;
        $this->responses = $responses;
    }

    /**
     * @param $statusCode
     * @param array $headers
     * @return Response
     */
    public function statusCode($statusCode, array $headers = [])
    {
        return $this->respond($statusCode, null, $headers);
    }

    /**
     * @param array $headers
     * @return Response
     */
    public function noContent(array $headers = [])
    {
        return $this->statusCode(Response::HTTP_NO_CONTENT, $headers);
    }

    /**
     * @param mixed $meta
     * @param int $statusCode
     * @param array $headers
     * @return Response
     */
    public function meta($meta, $statusCode = Response::HTTP_OK, array $headers = [])
    {
        $content = $this
            ->getEncoder()
            ->encodeMeta($meta);

        return $this->respond($statusCode, $content, $headers);
    }

    /**
     * @param mixed $data
     * @param array $links
     * @param mixed|null $meta
     * @param int $statusCode
     * @param array $headers
     * @return Response
     */
    public function content($data, array $links = [], $meta = null, $statusCode = Response::HTTP_OK, array $headers = [])
    {
        /** Eloquent collections do not encode properly, so we'll get all just in case it's an Eloquent collection */
        if ($data instanceof Collection) {
            $data = $data->all();
        }

        $content = $this
            ->getEncoder()
            ->withLinks($links)
            ->withMeta($meta)
            ->encodeData($data, $this->environment->getParameters());

        return $this->respond($statusCode, $content, $headers);
    }

    /**
     * @param object $resource
     * @param array $links
     * @param mixed|null $meta
     * @param array $headers
     * @return Response
     */
    public function created($resource, array $links = [], $meta = null, array $headers = [])
    {
        $encoder = $this->getEncoder();

        $content = $encoder
            ->withLinks($links)
            ->withMeta($meta)
            ->encodeData($resource, $this->getEncodingParameters());

        $subHref = $this
            ->environment
            ->getSchemas()
            ->getSchema($resource)
            ->getSelfSubLink($resource)
            ->getSubHref();

        return $this
            ->responses
            ->getCreatedResponse(
                $this->environment->getUrlPrefix() . $subHref,
                $this->environment->getEncoderMediaType(),
                $content,
                $this->environment->getSupportedExtensions(),
                $headers
            );
    }

    /**
     * @param object $resource
     * @param string $relationshipName
     * @param object $related
     * @param array $links
     * @param mixed|null $meta
     * @param mixed|null $selfLinkMeta
     * @param bool $selfLinkTreatAsHref
     * @param mixed|null $relatedLinkMeta
     * @param bool $relatedLinkTreatAsHref
     * @param array $headers
     * @return Response
     */
    public function relationship(
        $resource,
        $relationshipName,
        $related,
        array $links = [],
        $meta = null,
        $selfLinkMeta = null,
        $selfLinkTreatAsHref = false,
        $relatedLinkMeta = null,
        $relatedLinkTreatAsHref = false,
        array $headers = []
    ) {
        $content = $this
            ->getEncoder()
            ->withLinks($links)
            ->withMeta($meta)
            ->withRelationshipSelfLink($resource, $relationshipName, $selfLinkMeta, $selfLinkTreatAsHref)
            ->withRelationshipRelatedLink($resource, $relationshipName, $relatedLinkMeta, $relatedLinkTreatAsHref)
            ->encodeIdentifiers($related, $this->getEncodingParameters());

        return $this->respond(Response::HTTP_OK, $content, $headers);
    }

    /**
     * @param $statusCode
     * @param string|null $content
     * @param array $headers
     * @return Response
     */
    public function respond($statusCode, $content = null, array $headers = [])
    {
        return $this
            ->responses
            ->getResponse(
                (int) $statusCode,
                $this->environment->getEncoderMediaType(),
                $content,
                $this->environment->getSupportedExtensions(),
                $headers
            );
    }

    /**
     * @return EncoderInterface
     */
    public function getEncoder()
    {
        return $this->environment->getEncoder();
    }

    /**
     * @return \Neomerx\JsonApi\Contracts\Parameters\ParametersInterface
     */
    public function getEncodingParameters()
    {
        return $this->environment->getParameters();
    }
}