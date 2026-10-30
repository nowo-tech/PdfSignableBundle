<?php

declare(strict_types=1);
use Nowo\FormKitBundle\NowoFormKitBundle;
use Nowo\HotReloadBundle\NowoHotReloadBundle;
use Nowo\PdfSignableBundle\NowoPdfSignableBundle;
use Nowo\TwigInspectorBundle\NowoTwigInspectorBundle;
use Symfony\Bundle\DebugBundle\DebugBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Bundle\WebProfilerBundle\WebProfilerBundle;
use Twig\Extra\TwigExtraBundle\TwigExtraBundle;

return [
    FrameworkBundle::class         => ['all' => true],
    TwigBundle::class              => ['all' => true],
    DebugBundle::class             => ['dev' => true, 'test' => true],
    WebProfilerBundle::class       => ['dev' => true, 'test' => true],
    NowoFormKitBundle::class       => ['all' => true],
    NowoPdfSignableBundle::class   => ['all' => true],
    NowoHotReloadBundle::class     => ['dev' => true, 'test' => true],
    NowoTwigInspectorBundle::class => ['dev' => true, 'test' => true],
    TwigExtraBundle::class         => ['all' => true],
];
