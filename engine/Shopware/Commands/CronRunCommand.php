<?php
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @category  Shopware
 * @package   Shopware\Components\Console\Commands
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class CronRunCommand extends ShopwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('sw:cron:run')
            ->setDescription('Runs cronjobs.')
            ->setHelp(<<<EOF
The <info>%command.name%</info> runs due cronjobs.
EOF
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'If given, the file will be overwritten if it already exists'
             )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->container->load('plugins');

        /** @var \Enlight_Components_Cron_Manager $manager */
        $manager = $this->container->get('cron');

        $stack = array();
        while (($job = $manager->getNextJob($input->getOption('force'))) !== null && !isset($stack[$job->getId()])) {
            $stack[$job->getId()] = true;

            $output->writeln("Processing " . $job->getName());
            $manager->runJob($job);
        }
    }
}
