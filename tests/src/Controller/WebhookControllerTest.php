<?php

namespace Tests\PHPCensor\Controller;

use PHPCensor\Controller\WebhookController;

class WebhookControllerTest extends \PHPUnit\Framework\TestCase
{
    public function test_wrong_action_name_return_json_with_error()
    {
        $webController = new WebhookController(
            $this->prophesize('PHPCensor\Config')->reveal(),
            $this->prophesize('PHPCensor\Http\Request')->reveal(),
            $this->prophesize('PHPCensor\Http\Response')->reveal()
        );

        $error = $webController->handleAction('test', []);

        self::assertInstanceOf('PHPCensor\Http\Response\JsonResponse', $error);

        $responseData = $error->getData();
        self::assertEquals(500, $responseData['code']);

        self::assertEquals('failed', $responseData['body']['status']);

        self::assertEquals('application/json', $responseData['headers']['Content-Type']);

        // @todo: we can't text the result is JSON file with
        //   self::assertJson((string) $error);
        // since the flush method automatically add the header and break the
        // testing framework.
    }
}
