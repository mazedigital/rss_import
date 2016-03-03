<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

	<xsl:template match="/">
		<table>
			<xsl:for-each select='/jta-breaking-news/item'>
				<xsl:variable name='description' select='description'/>

				<tr guid='{guid}'>
					<td>
						<xsl:value-of select='title'/>
					</td>
					<td>
						<xsl:value-of select='$description'/>
						<input type='checkbox' name='items[{guid}]' id='{guid}'/>
					</td>
				</tr>
			</xsl:for-each>
		</table>
	</xsl:template>

</xsl:stylesheet>
