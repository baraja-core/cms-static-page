<?php

declare(strict_types=1);

namespace Baraja\StaticPage;


use Baraja\Doctrine\ORM\DI\OrmAnnotationsExtension;
use Baraja\Plugin\Component\VueComponent;
use Baraja\Plugin\PluginComponentExtension;
use Baraja\Plugin\PluginManager;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\ServiceDefinition;

final class StaticPageExtension extends CompilerExtension
{
	/**
	 * @return string[]
	 */
	public static function mustBeDefinedBefore(): array
	{
		return [OrmAnnotationsExtension::class];
	}


	public function beforeCompile(): void
	{
		PluginComponentExtension::defineBasicServices($builder = $this->getContainerBuilder());
		OrmAnnotationsExtension::addAnnotationPathToManager(
			$builder,
			'Baraja\StaticPage\Entity',
			__DIR__ . '/Entity',
		);

		$builder->addDefinition($this->prefix('staticPageManager'))
			->setFactory(StaticPageManager::class);

		/** @var ServiceDefinition $pluginManager */
		$pluginManager = $builder->getDefinitionByType(PluginManager::class);
		$pluginManager->addSetup(
			'?->addComponent(?)',
			[
				'@self',
				[
					'key' => 'staticPageDefault',
					'name' => 'static-page-default',
					'implements' => StaticPagePlugin::class,
					'componentClass' => VueComponent::class,
					'view' => 'default',
					'source' => __DIR__ . '/../template/default.js',
				],
			],
		);
		$pluginManager->addSetup(
			'?->addComponent(?)',
			[
				'@self',
				[
					'key' => 'staticPageOverview',
					'name' => 'static-page-overview',
					'implements' => StaticPagePlugin::class,
					'componentClass' => VueComponent::class,
					'view' => 'detail',
					'source' => __DIR__ . '/../template/overview.js',
					'position' => 100,
					'tab' => 'Overview',
					'params' => ['id'],
				],
			],
		);
	}
}
