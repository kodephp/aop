# Makefile for Kode/AOP

# 默认目标
.PHONY: help
help:
	@echo "Kode/AOP Makefile"
	@echo "================="
	@echo "Available targets:"
	@echo "  help          - 显示此帮助信息"
	@echo "  test          - 运行示例代码测试 AOP 功能"
	@echo "  clean         - 清理生成的文件"

# 运行示例代码测试 AOP 功能
.PHONY: test
test:
	@echo "Running AOP demo..."
	@php index.php

# 清理生成的文件
.PHONY: clean
clean:
	@echo "Cleaning up..."
	@rm -f *.log
	@echo "Done."

# 显示版本信息
.PHONY: version
version:
	@echo "Kode/AOP v1.0.0"