<?php

/**
 * This file is part of BraincraftedBootstrapBundle.
 *
 * (c) 2012-2013 by Florian Eckerstorfer
 */

namespace Braincrafted\Bundle\BootstrapBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Braincrafted\Bundle\BootstrapBundle\Util\PathUtil;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Kernel;

/**
 * GenerateCommand
 *
 * @package    BraincraftedBootstrapBundle
 * @subpackage Command
 * @author     Florian Eckerstorfer <florian@eckerstorfer.co>
 * @copyright  2012-2013 Florian Eckerstorfer
 * @license    http://opensource.org/licenses/MIT The MIT License
 * @link       http://bootstrap.braincrafted.com BraincraftedBootstrapBundle
 */
class GenerateCommand extends Command implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    /** @var PathUtil */
    private $pathUtil;

    /**
     * {@inheritDoc}
     */
    public function __construct($name = null)
    {
        $this->pathUtil = new PathUtil;

        parent::__construct($name);
    }

    /**
     * {@inheritDoc}
     *
     * @codeCoverageIgnore
     */
    protected function configure(): void
    {
        $this
            ->setName('braincrafted:bootstrap:generate')
            ->setDescription('Generates a custom bootstrap.less')
        ;
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = $this->getContainer()->getParameter('braincrafted_bootstrap.customize');

        if (false === isset($config['variables_file']) || null === $config['variables_file']) {
            $output->writeln('<error>Found no custom variables.less file.</error>');

            return Command::FAILURE;
        }

        $filter = $this->getContainer()->getParameter('braincrafted_bootstrap.css_preprocessor');
        if ('less' !== $filter && 'lessphp' !== $filter) {
            $output->writeln(
                '<error>Bundle must be configured with "less" or "lessphp" to generated bootstrap.less</error>'
            );

            return Command::FAILURE;
        }

        $output->writeln('<comment>Found custom variables file. Generating...</comment>');
        $this->executeGenerateBootstrap($config);
        $output->writeln(sprintf('Saved to <info>%s</info>', $config['bootstrap_output']));

        return Command::SUCCESS;
    }

    protected function getContainer(): ContainerInterface
    {
        if (null === $this->container) {
            throw new \LogicException('The container has not been set.');
        }

        return $this->container;
    }

    protected function executeGenerateBootstrap(array $config)
    {
        // In the template for bootstrap.less we need the path where Bootstraps .less files are stored and the path
        // to the variables.less file.
        // Absolute path do not work in LESSs import statement, we have to calculate the relative ones

        $lessDir = $this->pathUtil->getRelativePath(
            dirname($config['bootstrap_output']),
            $this->getContainer()->getParameter('braincrafted_bootstrap.assets_dir')
        );
        $variablesDir = $this->pathUtil->getRelativePath(
            dirname($config['bootstrap_output']),
            dirname($config['variables_file'])
        );
        $variablesFile = sprintf(
            '%s%s%s',
            $variablesDir,
            strlen($variablesDir) > 0 ? '/' : '',
            basename($config['variables_file'])
        );

        $container = $this->getContainer();

        if (Kernel::VERSION_ID >= 20500 && Kernel::VERSION_ID < 30000) {
            $container->enterScope('request');
            $container->set('request', new Request(), 'request');
        }

        // We can now use Twig to render the bootstrap.less file and save it
        $content = $container->get('twig')->render(
            $config['bootstrap_template'],
            array(
                'variables_file' => $variablesFile,
                'assets_dir'     => $lessDir
            )
        );
        file_put_contents($config['bootstrap_output'], $content);
    }
}
