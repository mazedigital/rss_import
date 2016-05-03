<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

	<xsl:template match="/">
		<table>
			<xsl:for-each select='/*/item'>

				<tr guid='{guid}'>
					<td>
						<xsl:value-of select='title'/>
						<a href="{link}" style='text-decoration:none;border:0;' target='_blank'><i class="fa fa-external-link"></i></a>
					</td>
					<td>
						<xsl:value-of select='description'/>
					</td>
					<td>
						<span date='{pubDate}'></span>
						<input type='checkbox' name='items[{guid}]' id='{guid}'/>
					</td>
				</tr>
			</xsl:for-each>
		</table>
	</xsl:template>

</xsl:stylesheet>
