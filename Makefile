install:
	composer install

serve: install
	php -S localhost:8000 -t public
