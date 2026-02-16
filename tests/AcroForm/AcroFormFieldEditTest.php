<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\AcroForm;

use Nowo\PdfSignableBundle\AcroForm\AcroFormFieldEdit;
use PHPUnit\Framework\TestCase;

final class AcroFormFieldEditTest extends TestCase
{
    public function testConstructorDefaults(): void
    {
        $edit = new AcroFormFieldEdit();
        self::assertSame('', $edit->fieldId);
        self::assertNull($edit->page);
        self::assertSame('', $edit->label);
        self::assertSame('text', $edit->controlType);
        self::assertSame('', $edit->rect);
        self::assertSame('', $edit->fieldName);
        self::assertSame('', $edit->options);
        self::assertSame('', $edit->defaultValue);
        self::assertFalse($edit->defaultChecked);
        self::assertSame('1', $edit->checkboxValueOn);
        self::assertSame('0', $edit->checkboxValueOff);
        self::assertSame('check', $edit->checkboxIcon);
        self::assertNull($edit->fontSize);
        self::assertSame('sans-serif', $edit->fontFamily);
        self::assertFalse($edit->fontAutoSize);
        self::assertNull($edit->maxLen);
        self::assertFalse($edit->hidden);
        self::assertFalse($edit->createIfMissing);
    }

    public function testFromArrayWithMinimalData(): void
    {
        $edit = AcroFormFieldEdit::fromArray(['fieldId' => 'f1']);
        self::assertSame('f1', $edit->fieldId);
        self::assertNull($edit->page);
        self::assertSame('text', $edit->controlType);
    }

    public function testFromArrayWithSnakeCaseKeys(): void
    {
        $edit = AcroFormFieldEdit::fromArray([
            'field_id' => 'f2',
            'default_value' => 'hello',
            'control_type' => 'textarea',
        ]);
        self::assertSame('f2', $edit->fieldId);
        self::assertSame('hello', $edit->defaultValue);
        self::assertSame('textarea', $edit->controlType);
    }

    public function testFromArrayWithRectArray(): void
    {
        $edit = AcroFormFieldEdit::fromArray([
            'fieldId' => 'f3',
            'rect' => [10.5, 20.0, 110.5, 40.0],
        ]);
        self::assertSame('10.5, 20.0, 110.5, 40.0', $edit->rect);
    }

    public function testFromArrayWithOptionsArray(): void
    {
        $edit = AcroFormFieldEdit::fromArray([
            'fieldId' => 'f5',
            'options' => [
                ['value' => 'a', 'label' => 'A'],
                ['value' => 'b'],
                'c',
            ],
        ]);
        self::assertSame("a|A\nb\nc", $edit->options);
    }

    public function testFromArrayWithFieldNameMaxLenHiddenCreateIfMissing(): void
    {
        $edit = AcroFormFieldEdit::fromArray([
            'fieldId' => 'f6',
            'field_name' => 'MyField',
            'maxLen' => 100,
            'hidden' => true,
            'createIfMissing' => true,
        ]);
        self::assertSame('MyField', $edit->fieldName);
        self::assertSame(100, $edit->maxLen);
        self::assertTrue($edit->hidden);
        self::assertTrue($edit->createIfMissing);
    }

    public function testFromArrayWithRectArrayLengthLessThanFourDoesNotSetRect(): void
    {
        $edit = AcroFormFieldEdit::fromArray([
            'fieldId' => 'f7',
            'rect' => [10, 20],
        ]);
        self::assertSame('', $edit->rect);
    }

    public function testFromArrayWithLabelAndFontFamily(): void
    {
        $edit = AcroFormFieldEdit::fromArray([
            'fieldId' => 'f8',
            'label' => 'Full Name',
            'fontSize' => 14,
            'fontFamily' => 'Helvetica',
        ]);
        self::assertSame('Full Name', $edit->label);
        self::assertSame(14, $edit->fontSize);
        self::assertSame('Helvetica', $edit->fontFamily);
    }

    public function testFromArrayWithEmptyOptionsArray(): void
    {
        $edit = AcroFormFieldEdit::fromArray([
            'fieldId' => 'f9',
            'options' => [],
        ]);
        self::assertSame('', $edit->options);
    }

    public function testFromArrayWithDefaultCheckedTrue(): void
    {
        $edit = AcroFormFieldEdit::fromArray([
            'fieldId' => 'f10',
            'defaultChecked' => true,
        ]);
        self::assertTrue($edit->defaultChecked);
    }
}
