#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#define MAX_LINE_LEN 1024

int main(void) {
    FILE *fp = fopen("data.csv", "r");
    double total = 0.0;

    if (fp == NULL) {
        /* File does not exist or cannot be opened */
        printf("%.2f", total);
        return 0;
    }

    char line[MAX_LINE_LEN];
    int lines_read = 0;

    while (fgets(line, sizeof(line), fp) != NULL) {
        /* Strip trailing newline / carriage return */
        line[strcspn(line, "\r\n")] = '\0';

        /* Skip empty lines */
        if (strlen(line) == 0) {
            continue;
        }

        /* Make a working copy since strtok modifies the string */
        char line_copy[MAX_LINE_LEN];
        strncpy(line_copy, line, sizeof(line_copy) - 1);
        line_copy[sizeof(line_copy) - 1] = '\0';

        /* Expected format: Name,Amount,Category */
        char *name = strtok(line_copy, ",");
        char *amount_str = strtok(NULL, ",");
        /* category is parsed but unused for the total */
        char *category = strtok(NULL, ",");
        (void)name;
        (void)category;

        if (amount_str == NULL) {
            /* Malformed line: no amount field, skip it */
            continue;
        }

        char *endptr;
        double amount = strtod(amount_str, &endptr);

        /* Only count it if strtod actually parsed a number */
        if (endptr != amount_str) {
            total += amount;
            lines_read++;
        }
    }

    fclose(fp);

    /* If file existed but was empty or had no valid data rows,
       total remains 0.0, which prints as 0.00 - matches spec. */
    printf("%.2f", total);

    return 0;
}
