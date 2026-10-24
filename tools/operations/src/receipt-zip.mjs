import { inflateRawSync } from 'node:zlib';
import { requireThat } from './contracts.mjs';

// This is deliberately not a filesystem extractor. A signed receipt archive
// admits one bounded ASCII-named file, no paths, ZIP64, encryption or extras.
export function receiptFromZip(zip) {
  requireThat(Buffer.isBuffer(zip) && zip.length >= 100 && zip.length <= 65536, 'QA_ARCHIVE', 'Invalid bounded receipt archive.');
  const end = zip.length - 22;
  requireThat(zip.readUInt32LE(end) === 0x06054b50 && zip.readUInt16LE(end + 20) === 0
    && zip.readUInt16LE(end + 4) === 0 && zip.readUInt16LE(end + 6) === 0
    && zip.readUInt16LE(end + 8) === 1 && zip.readUInt16LE(end + 10) === 1, 'QA_ARCHIVE', 'Expected a single-file receipt archive.');
  const directory = zip.readUInt32LE(end + 16); const directorySize = zip.readUInt32LE(end + 12);
  requireThat(directory + directorySize === end && directorySize >= 46 && directory < end - 45, 'QA_ARCHIVE', 'Invalid archive directory bounds.');
  requireThat(zip.readUInt32LE(directory) === 0x02014b50, 'QA_ARCHIVE', 'Invalid archive directory.');
  const flags = zip.readUInt16LE(directory + 8), method = zip.readUInt16LE(directory + 10);
  const crc = zip.readUInt32LE(directory + 16), compressed = zip.readUInt32LE(directory + 20), size = zip.readUInt32LE(directory + 24);
  const nameLength = zip.readUInt16LE(directory + 28), extraLength = zip.readUInt16LE(directory + 30), commentLength = zip.readUInt16LE(directory + 32);
  const offset = zip.readUInt32LE(directory + 42);
  requireThat((flags & ~0x808) === 0 && [0, 8].includes(method) && size > 0 && size <= 16384 && compressed <= 65536
    && offset === 0 && directorySize === 46 + nameLength + extraLength + commentLength && nameLength === 12
    && zip.subarray(directory + 46, directory + 46 + nameLength).toString() === 'receipt.json', 'QA_ARCHIVE', 'Only the bounded receipt.json payload is supported.');
  requireThat(zip.readUInt32LE(0) === 0x04034b50 && zip.readUInt16LE(6) === flags && zip.readUInt16LE(8) === method
    && zip.readUInt16LE(26) === nameLength && zip.subarray(30, 42).toString() === 'receipt.json', 'QA_ARCHIVE', 'Local and central archive entries disagree.');
  const start = 30 + nameLength + zip.readUInt16LE(28);
  requireThat(start + compressed <= directory, 'QA_ARCHIVE', 'Receipt payload overlaps the directory.');
  let bytes;
  try { bytes = method === 0 ? zip.subarray(start, start + compressed) : inflateRawSync(zip.subarray(start, start + compressed), { maxOutputLength: 16384 }); }
  catch { requireThat(false, 'QA_ARCHIVE', 'Receipt archive decompression failed.'); }
  requireThat(bytes.length === size, 'QA_ARCHIVE', 'Receipt size does not match.');
  let checksum = 0xffffffff;
  for (const byte of bytes) { checksum ^= byte; for (let bit = 0; bit < 8; bit++) checksum = (checksum >>> 1) ^ ((checksum & 1) ? 0xedb88320 : 0); }
  requireThat(((checksum ^ 0xffffffff) >>> 0) === crc, 'QA_ARCHIVE', 'Receipt archive checksum failed.');
  try { return JSON.parse(bytes.toString('utf8')); } catch { requireThat(false, 'QA_ARCHIVE', 'Receipt JSON is invalid.'); }
}
