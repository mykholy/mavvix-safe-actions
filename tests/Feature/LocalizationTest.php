<?php

declare(strict_types=1);

namespace Mavvix\SafeActions\Tests\Feature;

use Mavvix\SafeActions\Exceptions\UnsafeExecution;
use Mavvix\SafeActions\Exceptions\UnsafeSelection;
use Mavvix\SafeActions\Tests\TestCase;

final class LocalizationTest extends TestCase
{
    public function test_arabic_errors_and_modal_labels_are_loaded(): void
    {
        app()->setLocale('ar');

        self::assertSame('تأكيد التغييرات', __('safe-actions::messages.modal.confirm'));
        self::assertSame('عدد السجلات التي ستتغير', __('safe-actions::messages.modal.record_count'));
        self::assertSame('ليست لديك صلاحية تنفيذ العملية على سجل واحد أو أكثر.', UnsafeSelection::unauthorized()->getMessage());
        self::assertSame('تغيرت السجلات المحددة بعد المعاينة. أعد المعاينة قبل التنفيذ.', UnsafeExecution::selectionChanged()->getMessage());
    }

    public function test_english_remains_the_default_fallback(): void
    {
        app()->setLocale('en');

        self::assertSame('Confirm changes', __('safe-actions::messages.modal.confirm'));
        self::assertSame('One or more selected records are not authorized.', UnsafeSelection::unauthorized()->getMessage());
        self::assertSame('The permitted query changed after the preflight. Start a new preview.', UnsafeExecution::scopeChanged()->getMessage());
    }
}
