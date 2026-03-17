# Makefile for Kode/AOP

.PHONY: help
help:
	@echo "Kode/AOP Makefile"
	@echo "================="
	@echo "Available targets:"
	@echo "  help          - 显示此帮助信息"
	@echo "  test          - 运行测试"
	@echo "  example       - 运行示例代码"
	@echo "  coverage      - 生成测试覆盖率报告"
	@echo "  analyse       - 运行静态分析"
	@echo "  cs-check      - 检查代码风格"
	@echo "  cs-fix        - 修复代码风格"
	@echo "  clean         - 清理生成的文件"

.PHONY: test
test:
	@echo "Running tests..."
	@php vendor/bin/phpunit --testdox

.PHONY: example
example:
	@echo "Running AOP demo..."
	@php examples/demo.php

.PHONY: coverage
coverage:
	@echo "Generating coverage report..."
	@php vendor/bin/phpunit --coverage-html coverage

.PHONY: analyse
analyse:
	@echo "Running static analysis..."
	@php vendor/bin/phpstan analyse src tests --level=8

.PHONY: cs-check
cs-check:
	@echo "Checking code style..."
	@php vendor/bin/phpcs src tests

.PHONY: cs-fix
cs-fix:
	@echo "Fixing code style..."
	@php vendor/bin/phpcbf src tests

.PHONY: clean
clean:
	@echo "Cleaning up..."
	@rm -rf coverage/
	@rm -f .phpunit.result.cache
	@rm -f *.log
	@echo "Done."

.PHONY: version
version:
	@echo "Kode/AOP v2.1.0"
