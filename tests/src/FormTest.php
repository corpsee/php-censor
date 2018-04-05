<?php

namespace Tests\PHPCensor;

use PHPCensor\Form;

class FormTest extends \PHPUnit\Framework\TestCase
{
    public function testFormBasics()
    {
        $f = new Form();
        $f->setAction('/');
        $f->setMethod('POST');

        self::assertTrue($f->getAction() == '/');
        self::assertTrue($f->getMethod() == 'POST');
    }

    public function testElementBasics()
    {
        $f = new Form\Element\Text('element-name');
        $f->setId('element-id');
        $f->setLabel('element-label');
        $f->setClass('element-class');
        $f->setContainerClass('container-class');

        self::assertTrue($f->getName() == 'element-name');
        self::assertTrue($f->getId() == 'element-id');
        self::assertTrue($f->getLabel() == 'element-label');
        self::assertTrue($f->getClass() == 'element-class');
        self::assertTrue($f->getContainerClass() == 'container-class');

        $output = $f->render();

        self::assertTrue(is_string($output));
        self::assertTrue(!empty($output));
        self::assertTrue(strpos($output, 'container-class') !== false);
    }

    public function testInputBasics()
    {
        $f = new Form\Element\Text();

        $f->setValue('input-value');
        $f->setRequired(true);
        $f->setValidator(function ($value) {
            return ($value == 'input-value');
        });

        self::assertTrue($f->getValue() == 'input-value');
        self::assertTrue($f->getRequired() == true);
        self::assertTrue(is_callable($f->getValidator()));
    }

    public function testInputCreate()
    {
        $text = Form\Element\Text::create(
            'input-name',
            'input-label',
            true
        );

        self::assertEquals('input-name', $text->getName());
        self::assertEquals('input-label', $text->getLabel());
        self::assertTrue($text->getRequired());
    }

    public function testInputValidation()
    {
        $f = new Form\Element\Text();
        $f->setRequired(true);

        self::assertFalse($f->validate());

        $f->setRequired(false);
        $f->setPattern('input\-value');

        self::assertFalse($f->validate());

        $f->setValue('input-value');

        self::assertTrue($f->validate());

        $f->setValidator(function ($item) {
            if ($item != 'input-value') {
                throw new \Exception('Invalid input value.');
            }
        });

        self::assertTrue($f->validate());

        $f->setValue('fail');
        $f->setPattern(null);

        self::assertFalse($f->validate());
    }

    public function testInputValidationWithCustomError()
    {
        $f = new Form\Element\Text();
        $f->setRequired(true);
        $f->setValue('input-value');
        $f->setError('Error!');

        self::assertFalse($f->validate());
    }

    public function testFieldSetBasics()
    {
        $f  = new Form\FieldSet();
        $f2 = new Form\FieldSet('group');
        $f3 = new Form\FieldSet();

        $t = new Form\Element\Text('one');
        $t->setRequired(true);
        $f2->addField($t);

        $t = new Form\Element\Text('two');
        $f2->addField($t);

        $t = new Form\Element\Text('three');
        $f3->addField($t);

        $f->addField($f2);
        $f->addField($f3);

        self::assertFalse($f->validate());

        $f->setValues(['group' => ['one' => 'ONE', 'two' => 'TWO'], 'three' => 'THREE']);

        $values = $f->getValues();
        self::assertTrue(is_array($values));
        self::assertTrue(array_key_exists('group', $values));
        self::assertTrue(array_key_exists('one', $values['group']));
        self::assertTrue(array_key_exists('three', $values));
        self::assertTrue($values['group']['one'] == 'ONE');
        self::assertTrue($values['group']['two'] == 'TWO');
        self::assertTrue($values['three'] == 'THREE');
        self::assertTrue($f->validate());

        $html = $f->render();
        self::assertTrue(strpos($html, 'one') !== false);
        self::assertTrue(strpos($html, 'two') !== false);

        $children = $f->getChildren();
        self::assertEquals(2, count($children));
        self::assertEquals($f2, $children[$f2->getName()]);
        self::assertEquals($f3, $children[$f3->getName()]);

        $child = $f->getChild($f3->getName());
        self::assertEquals($f3, $child);
    }

    public function testElements()
    {
        $e = new Form\Element\Button();
        self::assertTrue($e->validate());
        self::assertTrue(strpos($e->render(), 'button') !== false);

        $e = new Form\Element\Checkbox();
        $e->setCheckedValue('ten');
        self::assertTrue($e->getCheckedValue() == 'ten');
        self::assertTrue(strpos($e->render(), 'checkbox') !== false);
        self::assertTrue(strpos($e->render(), 'checked') === false);

        $e->setValue(true);
        self::assertTrue(strpos($e->render(), 'checked') !== false);

        $e->setValue('ten');
        self::assertTrue(strpos($e->render(), 'checked') !== false);

        $e->setValue('fail');
        self::assertTrue(strpos($e->render(), 'checked') === false);

        $e = new Form\Element\CheckboxGroup();
        self::assertTrue(strpos($e->render(), 'group') !== false);

        $e = new Form\ControlGroup();
        self::assertTrue(strpos($e->render(), 'group') !== false);

        $e = new Form\Element\Email();
        self::assertTrue(strpos($e->render(), 'email') !== false);

        $e = new Form\Element\Select();
        $e->setOptions(['key' => 'Val']);
        $html = $e->render();
        self::assertTrue(strpos($html, 'select') !== false);
        self::assertTrue(strpos($html, 'option') !== false);
        self::assertTrue(strpos($html, 'key') !== false);
        self::assertTrue(strpos($html, 'Val') !== false);

        $e = new Form\Element\Submit();
        self::assertTrue($e->validate());
        self::assertTrue(strpos($e->render(), 'submit') !== false);

        $e = new Form\Element\Text();
        $e->setValue('test');
        self::assertTrue(strpos($e->render(), 'test') !== false);

        $e = new Form\Element\TextArea();
        $e->setRows(10);
        self::assertTrue(strpos($e->render(), '10') !== false);

        $e = new Form\Element\Url();
        self::assertTrue(strpos($e->render(), 'url') !== false);

        $_SESSION = [];

        $e = new Form\Element\Csrf();
        self::assertTrue(strpos($e->render(), $e->getValue()) !== false);
        self::assertEquals($_SESSION['csrf_tokens'][$e->getName()], $e->getValue());
        self::assertTrue($e->validate());
        $e->setValue('111');
        self::assertFalse($e->validate());

        $e = new Form\Element\Password();
        self::assertTrue(strpos($e->render(), 'password') !== false);
    }
}
