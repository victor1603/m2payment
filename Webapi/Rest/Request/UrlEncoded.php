<?php

namespace CodeCustom\Payments\Webapi\Rest\Request;

use Magento\Framework\App\State;
use Magento\Framework\Phrase;
use Magento\Framework\Webapi\Rest\Request\DeserializerInterface;

class UrlEncoded implements DeserializerInterface
{
    /**
     * @var State
     */
    protected $_appState;

    /**
     * Text constructor.
     *
     * @param State $appState
     */
    public function __construct(State $appState)
    {
        $this->_appState = $appState;
    }

    /**
     * Parse request body into array of params.
     *
     * @param string $body Posted content from request
     *
     * @return array|null Return NULL if content is invalid
     * @throws \Magento\Framework\Webapi\Exception
     */
    public function deserialize($body)
    {
        if (!\is_string($body)) {
            throw new \InvalidArgumentException(
                sprintf('"%s" data type is invalid. String is expected.', \gettype($body))
            );
        }
        try {
            $encodedBody = [];
            \parse_str($body, $encodedBody);
        } catch (\InvalidArgumentException $e) {
            if ($this->_appState->getMode() !== State::MODE_DEVELOPER) {
                throw new \Magento\Framework\Webapi\Exception(new Phrase('Decoding error.'));
            } else {
                throw new \Magento\Framework\Webapi\Exception(
                    new Phrase(
                        'Decoding error: %1%2%3%4',
                        [PHP_EOL, $e->getMessage(), PHP_EOL, $e->getTraceAsString()]
                    )
                );
            }
        }
        return $encodedBody;
    }
}