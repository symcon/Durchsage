<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/Validator.php';

class DurchsageValidationTest extends TestCaseSymconValidation
{
    public function testValidateDurchsage(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }

    public function testValidateAnnouncementModule(): void
    {
        $this->validateModule(__DIR__ . '/../Announcement');
    }
}