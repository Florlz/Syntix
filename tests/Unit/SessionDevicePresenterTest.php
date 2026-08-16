<?php

namespace Tests\Unit;

use App\Support\SessionDevicePresenter;
use PHPUnit\Framework\TestCase;

class SessionDevicePresenterTest extends TestCase
{
    public function test_it_presents_common_desktop_and_mobile_user_agents(): void
    {
        $edge = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/126.0.0.0 Safari/537.36 Edg/126.0.0.0';
        $android = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 Chrome/126.0.0.0 Mobile Safari/537.36';

        $this->assertSame('Microsoft Edge', SessionDevicePresenter::browser($edge));
        $this->assertSame('Windows', SessionDevicePresenter::platform($edge));
        $this->assertSame('Desktop', SessionDevicePresenter::deviceType($edge));

        $this->assertSame('Chrome', SessionDevicePresenter::browser($android));
        $this->assertSame('Android', SessionDevicePresenter::platform($android));
        $this->assertSame('Mobile', SessionDevicePresenter::deviceType($android));
    }

    public function test_it_uses_safe_fallbacks_for_unknown_user_agents(): void
    {
        $this->assertSame('Unknown browser', SessionDevicePresenter::browser('syntix-client/1.0'));
        $this->assertSame('Unknown platform', SessionDevicePresenter::platform('syntix-client/1.0'));
        $this->assertSame('Unknown device', SessionDevicePresenter::deviceType('syntix-client/1.0'));
    }
}
