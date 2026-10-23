<?php

namespace ContentEgg\application\components\command;

defined('\ABSPATH') || exit;

/**
 * CommandFactory class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

class CommandFactory
{
	static public function initAction()
	{
		if (!defined('\WP_CLI') || !\WP_CLI || !class_exists('\WP_CLI'))
			return;

		// Plugin::getInstance() runs on `init`, after WP-CLI has fired
		// `cli_init`: a hook added now would never run.
		if (\did_action('cli_init'))
			self::registerCommands();
		else
			\add_action('cli_init', [__CLASS__, 'registerCommands']);
	}

	static public function registerCommands()
	{
		// The commands are "cegg <name>": WP-CLI defers a subcommand until its
		// parent exists, so the "cegg" namespace is registered first.
		\WP_CLI::add_command('cegg', CeggCommandNamespace::class, ['shortdesc' => 'Content Egg commands.']);

		$command = new UpdateByKeywordCommand();
		self::registerCommand($command);
	}

	static public function registerCommand(CommandInterface $command)
	{
		\WP_CLI::add_command($command->getName(), $command, [
			'shortdesc' => $command->getDescription(),
			'synopsis' => $command->getSynopsis(),
		]);
	}
}
