<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sylius\Behat\Context\Setup;

use Behat\Behat\Context\Context;
use Doctrine\Common\Persistence\ObjectManager;
use Sylius\Bundle\ThemeBundle\Factory\ThemeFactoryInterface;
use Sylius\Bundle\ThemeBundle\Model\ThemeInterface;
use Sylius\Bundle\ThemeBundle\Repository\ThemeRepositoryInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Test\Services\SharedStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Zend\Hydrator\HydrationInterface;

/**
 * @author Kamil Kokot <kamil.kokot@lakion.com>
 */
final class ThemeContext implements Context
{
    /**
     * @var SharedStorageInterface
     */
    private $sharedStorage;

    /**
     * @var ThemeRepositoryInterface
     */
    private $themeRepository;

    /**
     * @var ThemeFactoryInterface
     */
    private $themeFactory;

    /**
     * @var ChannelRepositoryInterface
     */
    private $channelRepository;

    /**
     * @var ObjectManager
     */
    private $channelManager;

    /**
     * @var string
     */
    private $cacheDir;

    /**
     * @param SharedStorageInterface $sharedStorage
     * @param ThemeRepositoryInterface $themeRepository
     * @param ThemeFactoryInterface $themeFactory
     * @param ChannelRepositoryInterface $channelRepository
     * @param ObjectManager $channelManager
     * @param ContainerInterface $container
     */
    public function __construct(
        SharedStorageInterface $sharedStorage,
        ThemeRepositoryInterface $themeRepository,
        ThemeFactoryInterface $themeFactory,
        ChannelRepositoryInterface $channelRepository,
        ObjectManager $channelManager,
        ContainerInterface $container
    ) {
        $this->sharedStorage = $sharedStorage;
        $this->themeRepository = $themeRepository;
        $this->themeFactory = $themeFactory;
        $this->channelRepository = $channelRepository;
        $this->channelManager = $channelManager;
        $this->cacheDir = $container->getParameter('kernel.cache_dir');
    }

    /**
     * @Given the store has :themeTitle theme
     */
    public function storeHasTheme($themeTitle)
    {
        $themeName = str_replace(' ', '-', strtolower($themeTitle));

        $theme = $this->themeFactory->create($themeName, sprintf('%s/_themes/%s/', $this->cacheDir, $themeName));
        $theme->setTitle($themeTitle);

        if (!file_exists($theme->getPath())) {
            mkdir($theme->getPath(), 0777, true);
        }

        file_put_contents(
            rtrim($theme->getPath(), '/') . '/composer.json',
            sprintf('{ "name": "%s", "title": "%s" }', $themeName, $themeTitle)
        );

        $this->sharedStorage->set('theme', $theme);
    }

    /**
     * @Given channel :channel uses :theme theme
     */
    public function channelUsesTheme(ChannelInterface $channel, ThemeInterface $theme)
    {
        $channel->setTheme($theme);

        $this->channelManager->persist($channel);
        $this->channelManager->flush();

        $this->sharedStorage->set('channel', $channel);
        $this->sharedStorage->set('theme', $theme);
    }

    /**
     * @Given channel :channel does not use any theme
     */
    public function channelDoesNotUseAnyTheme(ChannelInterface $channel)
    {
        $channel->setTheme(null);

        $this->channelManager->flush();

        $this->sharedStorage->set('channel', $channel);
    }

    /**
     * @Given /^(this theme) changes homepage template contents to "([^"]+)"$/
     */
    public function themeChangesHomepageTemplateContents(ThemeInterface $theme, $contents)
    {
        $file = rtrim($theme->getPath(), '/') . '/SyliusWebBundle/views/Frontend/Homepage/main.html.twig';
        $dir = dirname($file);

        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($file, $contents);
    }
}
