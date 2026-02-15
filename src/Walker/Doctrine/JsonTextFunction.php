<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Walker\Doctrine;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

use function class_exists;

class JsonTextFunction extends FunctionNode
{
    private mixed $value;

    public function parse(Parser $parser): void
    {
        if (class_exists(TokenType::class)) {
            $parser->match(TokenType::T_IDENTIFIER);
            $parser->match(TokenType::T_OPEN_PARENTHESIS);
            $this->value = $parser->StringPrimary();
            $parser->match(TokenType::T_CLOSE_PARENTHESIS);

            return;
        }

        $parser->match(Lexer::T_IDENTIFIER); // @phpstan-ignore-line
        $parser->match(Lexer::T_OPEN_PARENTHESIS); // @phpstan-ignore-line
        $this->value = $parser->StringPrimary();
        $parser->match(Lexer::T_CLOSE_PARENTHESIS); // @phpstan-ignore-line
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        $valueSql = $sqlWalker->walkStringPrimary($this->value);
        $platform = $sqlWalker->getConnection()->getDatabasePlatform();

        if ($platform instanceof PostgreSQLPlatform) {
            return 'CAST(' . $valueSql . ' AS TEXT)';
        }

        if ($platform instanceof AbstractMySQLPlatform) {
            return 'CAST(' . $valueSql . ' AS CHAR)';
        }

        if ($platform instanceof SqlitePlatform) {
            return 'CAST(' . $valueSql . ' AS TEXT)';
        }

        return 'CAST(' . $valueSql . ' AS VARCHAR(65535))';
    }
}
