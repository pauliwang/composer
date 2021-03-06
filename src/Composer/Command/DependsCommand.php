<?php

/*
 * This file is part of Composer.
 *
 * (c) Nils Adermann <naderman@naderman.de>
 *     Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Composer\Command;

use Composer\DependencyResolver\Pool;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Justin Rainbow <justin.rainbow@gmail.com>
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class DependsCommand extends Command
{
    protected $linkTypes = array(
        'require' => 'requires',
        'require-dev' => 'devRequires',
    );

    protected function configure()
    {
        $this
            ->setName('depends')
            ->setDescription('Shows which packages depend on the given package')
            ->setDefinition(array(
                new InputArgument('package', InputArgument::REQUIRED, 'Package to inspect'),
                new InputOption('link-type', '', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Link types to show (require, require-dev)', array_keys($this->linkTypes)),
            ))
            ->setHelp(<<<EOT
Displays detailed information about where a package is referenced.

<info>php composer.phar depends composer/composer</info>

EOT
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $repos = $this->getComposer()->getRepositoryManager()->getLocalRepositories();
        $needle = $input->getArgument('package');

        $pool = new Pool();
        foreach ($repos as $repo) {
            $pool->addRepository($repo);
        }

        $packages = $pool->whatProvides($needle);
        if (empty($packages)) {
            throw new \InvalidArgumentException('Could not find package "'.$needle.'" in your project.');
        }

        $linkTypes = $this->linkTypes;

        $verbose = (bool) $input->getOption('verbose');
        $types = array_map(function ($type) use ($linkTypes) {
            $type = rtrim($type, 's');
            if (!isset($linkTypes[$type])) {
                throw new \InvalidArgumentException('Unexpected link type: '.$type.', valid types: '.implode(', ', array_keys($linkTypes)));
            }

            return $type;
        }, $input->getOption('link-type'));

        $dependsOnPackages = false;
        foreach ($repos as $repo) {
            $repo->filterPackages(function ($package) use ($needle, $types, $linkTypes, $output, $verbose, &$dependsOnPackages) {
                static $outputPackages = array();

                foreach ($types as $type) {
                    foreach ($package->{'get'.$linkTypes[$type]}() as $link) {
                        if ($link->getTarget() === $needle) {
                            $dependsOnPackages = true;
                            if ($verbose) {
                                $output->writeln($package->getPrettyName() . ' ' . $package->getPrettyVersion() . ' <info>' . $type . '</info> ' . $link->getPrettyConstraint());
                            } elseif (!isset($outputPackages[$package->getName()])) {
                                $output->writeln($package->getPrettyName());
                                $outputPackages[$package->getName()] = true;
                            }
                        }
                    }
                }
            });
        }

        if (!$dependsOnPackages) {
            $output->writeln('<info>There is no installed package depending on "'.$needle.'".</info>');
        }
    }
}
