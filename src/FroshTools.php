<?php declare(strict_types=1);

namespace Frosh\Tools;

use Frosh\Tools\DependencyInjection\CacheCompilerPass;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Yaml\Yaml;
use Frosh\Tools\Messenger\TaskLoggingMiddleware;

if (file_exists($vendorPath = __DIR__ . '/../vendor/autoload.php')) {
    require_once $vendorPath;
}

class FroshTools extends Plugin
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new CacheCompilerPass());

        $configPath = $this->getYamlPath($container);
        $container->setParameter('frosh_tools.yaml_path', $configPath);

        if (file_exists($configPath)) {
            $pathInfo = pathinfo($configPath);
            $loader = new YamlFileLoader($container, new FileLocator($pathInfo['dirname']));
            $loader->load($pathInfo['basename']);
        }

        parent::build($container);
    }

    public function activate(ActivateContext $activateContext): void
    {
        $this->updateYamlFile();
    }

    public function update(UpdateContext $updateContext): void
    {
        $this->updateYamlFile();
    }

    private function updateYamlFile(): void
    {
        $data['framework']['messenger']['buses']['messenger.bus.shopware']['middleware'] = [TaskLoggingMiddleware::class];
        file_put_contents($this->getYamlPath(), Yaml::dump($data));
    }

    private function getYamlPath($container = null): string
    {
        if (!$container && $this->container) {
            $container = $this->container;
        }

        return $container->getParameter('kernel.project_dir') . '/var/frosh_tools.yaml';
    }
}
