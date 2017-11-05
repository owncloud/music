# coding=utf-8
#
# This file is licensed under the Affero General Public License version 3 or
# later. See the COPYING file.
#
# @author Pauli Järvinen <pauli.jarvinen@gmail.com>
# @copyright Pauli Järvinen 2017
 
import argparse
import os

def ensure_parent_dir_exists(file_path):
	directory = os.path.dirname(file_path)
	if not os.path.exists(directory):
		os.makedirs(directory)
	
def clone_lang(l10n_path, src_lang, dst_lang):
	src_path = os.path.join(l10n_path, src_lang, 'music.po')
	dst_path = os.path.join(l10n_path, dst_lang, 'music.po')

	with open(src_path) as f:
		content = f.read()

	content = content.replace('Language: ' + src_lang, 'Language: ' + dst_lang, 1)

	ensure_parent_dir_exists(dst_path)
	# Write the file in binary mode to keep the Unix-style endlines even if running on Windows
	with open(dst_path, "wb") as f:
		f.write('# Cloned from ' + src_path + ' by duplicate-lang.py\n')
		f.write(content)

if __name__ == '__main__':
	parser = argparse.ArgumentParser(description='Clone localizations from language to another')
	parser.add_argument('mapping', nargs='+',
						help="mapping from source language to destination language in format 'src:dst', e.g. 'fi_FI:fi'")
	parser.add_argument('-p', '--path', help='path to the l10n directory')

	args = parser.parse_args()

	for mapping in args.mapping:
		src, dst = mapping.split(':')
		clone_lang(args.path, src, dst)
