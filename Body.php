<?php

declare(strict_types=1);

namespace Inspira\Http;

use Psr\Http\Message\StreamInterface;
use RuntimeException;

abstract class Body implements StreamInterface
{
	protected mixed $stream;

	/**
	 * Close the stream and the underlying resources upon class destruction.
	 */
	public function __destruct()
	{
		$this->close();
	}

	/**
	 * {@inheritdoc}
	 */
	public function __toString(): string
	{
		$this->seek(0);
		return (string) stream_get_contents($this->stream);
	}

	/**
	 * {@inheritdoc}
	 */
	public function close(): void
	{
		if (!empty($this->stream)) {
			fclose($this->stream);
			$this->stream = null;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function detach() : mixed
	{
		$detachedStream = $this->stream;
		$this->stream = null;

		return $detachedStream;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getSize(): ?int
	{
		$stat = fstat($this->stream);

		return $stat ? $stat['size'] : null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function tell(): int
	{
		$position = ftell($this->stream);

		if ($position === false) {
			throw new RuntimeException("Failed to get stream position");
		}

		return $position;
	}

	/**
	 * {@inheritdoc}
	 */
	public function eof(): bool
	{
		return feof($this->stream);
	}

	/**
	 * {@inheritdoc}
	 */
	public function isSeekable(): bool
	{
		$meta = stream_get_meta_data($this->stream);

		return $meta['seekable'];
	}

	/**
	 * {@inheritdoc}
	 */
	public function seek(int $offset, int $whence = SEEK_SET): void
	{
		$result = fseek($this->stream, $offset, $whence);

		if ($result === -1) {
			throw new RuntimeException("Failed to seek stream");
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function rewind(): void
	{
		$this->seek(0);
	}

	/**
	 * {@inheritdoc}
	 */
	public function isWritable(): bool
	{
		$meta = stream_get_meta_data($this->stream);
		$mode = strtolower($meta['mode']);

		return str_contains($mode, 'w') || str_contains($mode, 'w+');
	}

	/**
	 * {@inheritdoc}
	 */
	public function write(string $string): int
	{
		if (!$this->isWritable()) {
			throw new RuntimeException("Stream is not writable");
		}

		$result = fwrite($this->stream, $string);

		if ($result === false) {
			throw new RuntimeException("Failed to write stream");
		}

		return $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function isReadable(): bool
	{
		$meta = stream_get_meta_data($this->stream);
		$mode = strtolower($meta['mode']);

		return str_contains($mode, 'r') || str_contains($mode, 'r+');
	}

	/**
	 * {@inheritdoc}
	 */
	public function read(int $length): string
	{
		if (!$this->isReadable()) {
			throw new RuntimeException("Stream is not readable");
		}

		$result = fread($this->stream, $length);

		if ($result === false) {
			throw new RuntimeException("Failed to read stream");
		}

		return $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getContents(): string
	{
		$contents = stream_get_contents($this->stream);

		if ($contents === false) {
			throw new RuntimeException("Error reading stream contents");
		}

		return $contents;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getMetadata(?string $key = null): mixed
	{
		$meta = stream_get_meta_data($this->stream);

		if (empty($key)) {
			return $meta;
		}

		return $meta[$key] ?? null;
	}
}